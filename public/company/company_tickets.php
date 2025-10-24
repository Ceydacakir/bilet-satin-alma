<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

if (!isset($_SESSION['company_id'])) {
    header('Location: /login.php');
    exit();
}
$company_id = $_SESSION['company_id'];

// Bilet iptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_ticket'])) {
    
    $ticket_id = $_POST['ticket_id'];
    $company_id = $_SESSION['company_id'];
    
    if ($ticket_id > 0) {
        $result = cancelTicketByCompany($pdo, $ticket_id, $company_id);
        
        if ($result['success']) {
            setSuccessMessage($result['message']);
        } else {
            setErrorMessage($result['message']);
        }
        
        header('Location: company_tickets.php'); 
        exit();
        
    } else {
        setErrorMessage('Geçersiz bilet ID.');
        header('Location: company_tickets.php');
        exit();
    }
}

// Kullanıcının biletlerini al - rol bazlı sorgu
if ($_SESSION['role'] === 'company') {
    // Firma admin - kendi firmasının tüm biletleri
    $stmt = $pdo->prepare("
        SELECT t.*,tr.id as trip_id, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
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
}
$tickets = $stmt->fetchAll();

function groupTicketsByTrip(array $tickets): array {
    $grouped_tickets = [];
    foreach ($tickets as $ticket) {

        $trip_key = $ticket['trip_id']; 
        if (!isset($grouped_tickets[$trip_key])) {
            $grouped_tickets[$trip_key] = [
                'departure_city' => $ticket['departure_city'],
                'destination_city' => $ticket['destination_city'],
                'departure_date' => formatDate($ticket['departure_time'], 'd.m.Y'),
                'departure_time' => formatDate($ticket['departure_time'], 'H:i'),
                'company_name' => $ticket['company_name'],
                'tickets' => []
            ];
        }
        $grouped_tickets[$trip_key]['tickets'][] = $ticket;
    }
    return $grouped_tickets;
}

$grouped_trips = [];
if (!empty($tickets)) {
    $grouped_trips = groupTicketsByTrip($tickets);
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="text-white">
                <i class="fas fa-table me-2"></i>Satılan Biletler
            </h2>
            <p class="text-muted">
                Biletleriniz seferlere göre gruplanmıştır.
            </p>
        </div>
    </div>

    <?php if (empty($grouped_trips)): ?>
        <div class="card bg-dark border-secondary">
            <div class="card-body text-center py-5">
                <i class="fas fa-rocket fa-3x text-muted mb-3"></i>
                <h5 class="text-white">Henüz Bilet Satışı Yok</h5>
                <p class="text-muted">Müşterileriniz bilet aldığında burada görünecektir.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($grouped_trips as $trip_key => $trip_data): ?>
            
            <div class="card bg-dark border-secondary">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-12">
                            <h4 class="text-info">
                                <i class="fas fa-route me-2"></i>
                                Sefer: 
                                <?php echo h($trip_data['departure_city']); ?> → <?php echo h($trip_data['destination_city']); ?> (<?php echo h($trip_data['departure_date']); ?> - <?php echo h($trip_data['departure_time']); ?>)
                            </h4>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Yolcu Adı</th>
                                    <th>Yolcu E-posta</th>
                                    <th>Koltuk No.</th>
                                    <th>Fiyat</th>
                                    <th>Durum</th>
                                    <th>Satın Alım Zamanı</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trip_data['tickets'] as $ticket): 
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
                                        default:
                                            $status_class = 'bg-secondary';
                                            $status_text = 'Süresi Dolmuş';
                                            break;
                                    }
                                    $can_cancel = function_exists('canCancelTicket') && canCancelTicket($ticket['departure_time']);
                                ?>
                                    <tr>
                                        <td><?php echo h($ticket['id']); ?></td>
                                        <td>
                                            <?php 
                                                if (isset($_SESSION['role']) && $_SESSION['role'] !== 'user') {
                                                    echo h($ticket['passenger_name']); 
                                                } else {
                                                    echo 'Gizli';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if (isset($_SESSION['role']) && $_SESSION['role'] !== 'user') {
                                                    echo h($ticket['passenger_email']); 
                                                } else {
                                                    echo 'Gizli';
                                                }
                                            ?>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo h($ticket['seat_numbers']); ?></span></td>
                                        <td><?php echo formatPrice($ticket['total_price']); ?></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        <td><?php echo formatDate($ticket['created_at'], 'd.m.Y H:i'); ?></td>
                                        <td style="min-width: 150px;">
                                            <?php if ($ticket['status'] === 'active'): ?>
                                                <a href="/download_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-info btn-sm me-1" target="_blank" title="PDF İndir">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if ($can_cancel): ?>
                                                    <button type="button" 
                                                            class="btn btn-danger btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#cancelModal"
                                                            data-id="<?php echo $ticket['id']; ?>"
                                                            data-username="<?php  if (isset($_SESSION['role']) && $_SESSION['role'] !== 'user') {
                                                                echo h($ticket['passenger_name']); 
                                                            } else {
                                                                echo 'Gizli';
                                                            } ?>"
                                                            data-departure="<?php echo h($ticket['departure_city']); ?>"
                                                            data-destination="<?php echo h($ticket['destination_city']); ?>"
                                                            data-seat="<?php echo h($ticket['seat_numbers']) ?? 'Belirtilmemiş'; ?>"
                                                            data-date="<?php echo formatDate($ticket['departure_time']); ?>"
                                                            title="İptal Et">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-warning" title="Kalkışa 1 saatten az kaldı">İptal Edilemez</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <hr class="border-secondary">
        <?php endforeach; ?>
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
                        <p class="mb-2"><strong>Bilet Sahibi:</strong> <span id="modalUsername"></span></p>
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
                const username = button.getAttribute('data-username');
                const ticketId = button.getAttribute('data-id');
                
                // Modal içindeki alanları güncelle
                document.getElementById('modalRoute').textContent = `${departure} - ${destination}`;
                document.getElementById('modalSeat').textContent = seat;
                document.getElementById('modalDate').textContent = date;
                document.getElementById('modalUsername').textContent = username;
                document.getElementById('modalTicketId').value = ticketId;
            });
        }
});
</script>
</body>
</html>