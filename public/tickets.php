<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Giriş yapmış kullanıcı kontrolü
requireRole('user');

// Bilet iptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket'])) {
    
    $ticket_id = $_POST['ticket_id'];
    
    if ($ticket_id > 0) {
        $result = cancelTicketByUser($pdo, $ticket_id, $_SESSION['user_id']);
        
        if ($result['success']) {
            setSuccessMessage($result['message']);
        } else {
            setErrorMessage($result['message']);
        } 
    } else {
        setErrorMessage('Geçersiz bilet ID.');
    }
    header('Location: tickets.php');
    exit();
}

// Kullanıcının biletlerini al
if ($_SESSION['role'] === 'user') {
    // Önce aktif ama artık süresi geçmiş (tamamlanmış) biletleri "expired" yap
    $stmt = $pdo->prepare("
        UPDATE tickets
        SET status = 'expired'
        WHERE user_id = ?
          AND status = 'active'
          AND trip_id IN (
              SELECT id FROM trips WHERE arrival_time < DATETIME('now')
          )
    ");
    $stmt->execute([$_SESSION['user_id']]);

    //  Güncel biletleri çek
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
               bc.name AS company_name, GROUP_CONCAT(bs.seat_number) AS seat_numbers,
               u.full_name AS passenger_name, u.email AS passenger_email
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
}

$tickets = $stmt->fetchAll();


// Kullanıcının seferi tamamen silinmiş biletlerini al
if ($_SESSION['role'] === 'user') {
    $stmt_count = $pdo->prepare("
    SELECT 
        COUNT(t.id) as count
    FROM 
        tickets t
    LEFT JOIN  
        trips tr ON t.trip_id = tr.id
    WHERE 
        t.user_id = ?
        AND tr.id IS NULL
    ");
    $stmt_count->execute([$_SESSION['user_id']]);
    $result = $stmt_count->fetch(PDO::FETCH_ASSOC);

    // Sayıyı değişkene atama
    $deleted_trip_ticket_count = $result['count'];
}

// Kullanıcı bilgilerini al
$user = getUserData($pdo, $_SESSION['user_id']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <!-- Başlık ve Bakiye -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="text-white">
                <i class="fas fa-rocket me-2"></i>Bilet Yönetimi
            </h2>
            <p class="text-muted">
                Tüm biletlerinizi buradan görüntüleyebilir ve yönetebilirsiniz.
            </p>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark border-secondary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">
                        <i class="fas fa-rocket me-1"></i>Mevcut Bakiye
                    </h6>
                    <h4 class="text-primary mb-0"><?php echo formatPrice($user['balance']); ?></h4>
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
                    Henüz Biletiniz Yok
                </h5>
                <p class="text-muted">
                    İlk biletinizi almak için sefer arayabilirsiniz.
                </p>
                <a href="/search.php" class="btn btn-primary">
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
                                            $status_text = 'Geçmiş Sefer';
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
                                <?php if ($ticket['status'] === 'active'): ?>
                                    <a href="download_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                                        <i class="fas fa-download me-1"></i>PDF İndir
                                    </a>
                                    <?php if (canCancelTicket($ticket['departure_time'])): ?>
                                        <button type="button" 
                                                class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#cancelModal"
                                                data-id="<?php echo $ticket['id']; ?>"
                                                data-departure="<?php echo h($ticket['departure_city']); ?>"
                                                data-destination="<?php echo h($ticket['destination_city']); ?>"
                                                data-seat="<?php echo h($ticket['seat_numbers']) ?? 'Belirtilmemiş'; ?>"
                                                data-date="<?php echo formatDate($ticket['departure_time']); ?>">
                                            <i class="fas fa-times me-1"></i>İptal Et
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock me-1"></i>İptal Edilemez
                                        </span>
                                        <small class="d-block text-muted">Kalkışa 1 saatten az kaldı</small>
                                    <?php endif; ?>
                                <?php elseif ($ticket['status'] === 'cancelled'): ?>
                                    <span class="badge bg-danger">İptal Edildi</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Süresi Dolmuş</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($deleted_trip_ticket_count > 0): ?>
        <div class="card bg-dark border-secondary" style="margin-top:32px;">
            <div class="card-body text-center">
                <p class="text-white"><strong>Firma Tarafından İptal Edilmiş Seferlere Ait Bilet Sayısı: <?php echo $deleted_trip_ticket_count; ?></strong></p>
                <p class="text-white">Bu biletlerin iadesi tarafınıza yapıldı. Detaylı bilgi için firma yetkilisi ile iletişime geçin.</p>
            </div>
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
                <form method="POST">
                    <input type="hidden" name="ticket_id" id="modalTicketId"> 
                    <button type="submit" name="cancel_ticket" class="btn btn-danger">İptal Et</button> 
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cancelModal = document.getElementById('cancelModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function(event) {
            // Modalı tetikleyen butonu al
            const button = event.relatedTarget;
            
            // Butonun data-* niteliklerinden bilet bilgilerini al
            const departure = button.getAttribute('data-departure');
            const destination = button.getAttribute('data-destination');
            const seat = button.getAttribute('data-seat');
            const date = button.getAttribute('data-date');
            const ticketId = button.getAttribute('data-id');
            
            // Modal içindeki alanları güncelle
            document.getElementById('modalRoute').textContent = `${departure} - ${destination}`;
            document.getElementById('modalSeat').textContent = seat;
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalTicketId').value = ticketId;
        });
    }
});
</script>
</body>
</html>