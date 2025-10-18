<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - user ve admin/company (admin özelliği ile) erişebilir
requireLogin();

$is_admin_view = false;
if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'company') {
    $is_admin_view = true;
    // Admin/Company kullanıcıları tüm biletleri görebilir
} elseif ($_SESSION['role'] !== 'user') {
    setErrorMessage('Bu sayfaya erişim yetkiniz yok.');
    header('Location: index.php');
    exit();
}

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
                
                setSuccessMessage('Bilet başarıyla iptal edildi. Ücret bakiyenize iade edildi. 🚀');
                
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

// Admin bilet iptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_cancel_ticket']) && $is_admin_view) {
    $ticket_id = $_POST['ticket_id'];
    
    // Bilet bilgilerini al
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_time, u.id as user_id
        FROM tickets t 
        JOIN trips tr ON t.trip_id = tr.id 
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND t.status = 'active'
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if ($ticket) {
        try {
            $pdo->beginTransaction();
            
            // Bilet durumunu güncelle
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$ticket_id]);
            
            // Rezerve koltukları sil
            $stmt = $pdo->prepare("DELETE FROM booked_seats WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);
            
            // Kullanıcının bakiyesini geri yükle
            updateUserBalance($pdo, $ticket['user_id'], $ticket['total_price']);
            
            $pdo->commit();
            
            setSuccessMessage('Bilet admin yetkisiyle başarıyla iptal edildi. Ücret kullanıcının bakiyesine iade edildi. 🚀');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setErrorMessage('Bilet iptal edilirken bir hata oluştu.');
        }
    } else {
        setErrorMessage('Bilet bulunamadı veya iptal edilemez.');
    }
}

// Biletleri al - admin/company tüm biletleri, user sadece kendi biletlerini görür
if ($is_admin_view) {
    if ($_SESSION['role'] === 'admin') {
        // Admin tüm biletleri görür
        $stmt = $pdo->prepare("
            SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
                   bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers,
                   u.full_name as passenger_name, u.email as passenger_email
            FROM tickets t
            JOIN trips tr ON t.trip_id = tr.id
            JOIN bus_companies bc ON tr.company_id = bc.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
    } else {
        // Company admin sadece kendi firma biletlerini görür
        $stmt = $pdo->prepare("
            SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
                   bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers,
                   u.full_name as passenger_name, u.email as passenger_email
            FROM tickets t
            JOIN trips tr ON t.trip_id = tr.id
            JOIN bus_companies bc ON tr.company_id = bc.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
            WHERE bc.id = ?
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$_SESSION['company_id']]);
    }
    $tickets = $stmt->fetchAll();
} else {
    // Normal user sadece kendi biletlerini görür
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
               bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_companies bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        WHERE t.user_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets = $stmt->fetchAll();
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
</head>
<body class="dark-theme">
    <!-- Background Elements -->
    <div class="rockets"></div>
    <div class="space-particles"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-rocket me-2 rocket-icon"></i>HopBilet
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
                            <li><a class="dropdown-item" href="account.php">Hesabım</a></li>
                            <li><a class="dropdown-item active" href="tickets.php"><?php echo $is_admin_view ? 'Bilet Yönetimi' : 'Biletlerim'; ?></a></li>
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
                    <i class="fas fa-rocket me-2 rocket-icon"></i><?php echo $is_admin_view ? ($_SESSION['role'] === 'admin' ? 'Tüm Biletler' : 'Firma Biletleri') : 'Biletlerim'; ?>
                </h2>
                <p class="text-muted">
                    <?php if ($is_admin_view): ?>
                        <?php echo $_SESSION['role'] === 'admin' ? 'Sistemdeki tüm biletleri görüntüleyebilir ve yönetebilirsiniz.' : 'Firmanızın tüm biletlerini görüntüleyebilir ve yönetebilirsiniz.'; ?>
                    <?php else: ?>
                        Tüm biletlerinizi buradan görüntüleyebilir ve yönetebilirsiniz.
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4">
                <?php if (!$is_admin_view): ?>
                <div class="card credits-card">
                    <div class="card-body text-center">
                        <h6 class="mb-1 opacity-75">Mevcut Kredi</h6>
                        <h4 class="mb-2"><?php echo formatPrice($_SESSION['balance']); ?></h4>
                        <a href="account.php" class="btn btn-light btn-sm">
                            <i class="fas fa-rocket me-1"></i>Kredi Yükle
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-1">Toplam Bilet</h6>
                        <h4 class="text-primary mb-0"><?php echo count($tickets); ?></h4>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- Biletler -->
        <?php if (empty($tickets)): ?>
            <div class="card bg-dark border-secondary">
                <div class="card-body text-center py-5">
                    <i class="fas fa-rocket fa-3x text-muted mb-3"></i>
                    <h5 class="text-white"><?php echo $is_admin_view ? 'Henüz Bilet Yok' : 'Henüz Biletiniz Yok'; ?></h5>
                    <p class="text-muted">
                        <?php echo $is_admin_view ? 'Sistemde henüz bilet bulunmamaktadır.' : 'İlk biletinizi almak için sefer arayabilirsiniz.'; ?>
                    </p>
                    <?php if (!$is_admin_view): ?>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Sefer Ara
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($tickets as $ticket): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="ticket-card h-100">
                            <div class="card-body">
                                <!-- Bilet Başlığı -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="text-white mb-1">
                                            <i class="fas fa-rocket me-2 rocket-icon"></i><?php echo h($ticket['departure_city']); ?> → <?php echo h($ticket['destination_city']); ?>
                                        </h5>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-building me-1"></i><?php echo h($ticket['company_name']); ?>
                                        </p>
                                        <?php if ($is_admin_view && isset($ticket['passenger_name'])): ?>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-user me-1"></i><?php echo h($ticket['passenger_name']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        $status_icon = '';
                                        switch ($ticket['status']) {
                                            case 'active':
                                                $status_class = 'bg-success';
                                                $status_text = 'Aktif';
                                                $status_icon = 'fas fa-check';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-danger';
                                                $status_text = 'İptal';
                                                $status_icon = 'fas fa-times';
                                                break;
                                            case 'expired':
                                                $status_class = 'bg-secondary';
                                                $status_text = 'Süresi Dolmuş';
                                                $status_icon = 'fas fa-clock';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Bilet Bilgileri -->
                                <div class="row mb-3">
                                    <div class="col-4">
                                        <div class="p-3 bg-secondary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-calendar me-1"></i>Tarih
                                            </h6>
                                            <small class="text-primary"><?php echo formatDate($ticket['departure_time'], 'd.m.Y'); ?></small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-3 bg-secondary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-clock me-1"></i>Saat
                                            </h6>
                                            <small class="text-success"><?php echo formatDate($ticket['departure_time'], 'H:i'); ?></small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-3 bg-secondary rounded text-center">
                                            <h6 class="text-white mb-1">
                                                <i class="fas fa-chair me-1"></i>Koltuk
                                            </h6>
                                            <small class="text-warning"><?php echo h($ticket['seat_numbers']); ?></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fiyat -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Bilet Fiyatı:</span>
                                    <h5 class="text-primary mb-0"><?php echo formatPrice($ticket['total_price']); ?></h5>
                                </div>

                                <!-- Aksiyon Butonları -->
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($ticket['status'] == 'active' && !$is_admin_view): ?>
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
                                    <?php endif; ?>
                                    
                                    <?php if ($ticket['status'] == 'active'): ?>
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="downloadTicket(<?php echo $ticket['id']; ?>)">
                                            <i class="fas fa-download me-1"></i>PDF İndir
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-outline-info btn-sm" 
                                            onclick="viewTicketDetails(<?php echo $ticket['id']; ?>, '<?php echo addslashes(json_encode($ticket)); ?>')">
                                        <i class="fas fa-eye me-1"></i>Detaylar
                                    </button>

                                    <?php if ($is_admin_view && $ticket['status'] == 'active'): ?>
                                        <button class="btn btn-outline-warning btn-sm" 
                                                onclick="adminCancelTicket(<?php echo $ticket['id']; ?>)">
                                            <i class="fas fa-user-shield me-1"></i>Admin İptal
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- İptal Onay Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-exclamation-triangle me-2"></i>Bilet İptal Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white">Bu bilet iptal edilecek ve ücret bakiyenize iade edilecektir. Emin misiniz?</p>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Bu işlem geri alınamaz!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hayır</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="ticket_id" id="cancelTicketId">
                        <input type="hidden" name="cancel_ticket" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Evet, İptal Et
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin İptal Modal -->
    <div class="modal fade" id="adminCancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-user-shield me-2"></i>Admin Bilet İptali
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white">Bu bileti admin yetkisiyle iptal etmek istediğinizden emin misiniz?</p>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Bu işlem kullanıcının bakiyesine iade yapacak ve geri alınamaz!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="ticket_id" id="adminCancelTicketId">
                        <input type="hidden" name="admin_cancel_ticket" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-user-shield me-2"></i>Admin İptal Et
                        </button>
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
                <div class="modal-body" id="ticketDetailsContent">
                    <!-- Bilet detayları buraya yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" onclick="printTicketDetails()">
                        <i class="fas fa-print me-2"></i>Yazdır
                    </button>
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

        function adminCancelTicket(ticketId) {
            document.getElementById('adminCancelTicketId').value = ticketId;
            new bootstrap.Modal(document.getElementById('adminCancelModal')).show();
        }

        function downloadTicket(ticketId) {
            // PDF indirme işlemi
            window.open('download_ticket.php?id=' + ticketId, '_blank');
        }

        function viewTicketDetails(ticketId, ticketData) {
            try {
                const ticket = JSON.parse(ticketData);
                
                const content = `
                    <div class="ticket-details">
                        <div class="row mb-4">
                            <div class="col-12 text-center">
                                <h4 class="text-primary mb-0">
                                    <i class="fas fa-rocket me-2"></i>HopBilet
                                </h4>
                                <p class="text-muted">Bilet #${ticket.id}</p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-white">Güzergah</h6>
                                <p class="text-primary h5">${ticket.departure_city} → ${ticket.destination_city}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-white">Firma</h6>
                                <p class="text-success">${ticket.company_name}</p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-white">Kalkış Tarihi & Saati</h6>
                                <p class="text-warning">${new Date(ticket.departure_time).toLocaleString('tr-TR')}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-white">Varış Tarihi & Saati</h6>
                                <p class="text-info">${new Date(ticket.arrival_time).toLocaleString('tr-TR')}</p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-white">Koltuk Numarası</h6>
                                <p class="text-primary h5">${ticket.seat_numbers}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-white">Bilet Fiyatı</h6>
                                <p class="text-success h5">${formatPrice(ticket.total_price)}</p>
                            </div>
                        </div>
                        
                        ${ticket.passenger_name ? `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="text-white">Yolcu Adı</h6>
                                <p class="text-white">${ticket.passenger_name}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-white">E-posta</h6>
                                <p class="text-muted">${ticket.passenger_email}</p>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-white">Satın Alma Tarihi</h6>
                                <p class="text-muted">${new Date(ticket.created_at).toLocaleString('tr-TR')}</p>
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('ticketDetailsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('ticketDetailsModal')).show();
            } catch (e) {
                alert('Bilet detayları yüklenirken bir hata oluştu.');
            }
        }

        function printTicketDetails() {
            const content = document.getElementById('ticketDetailsContent').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Bilet Detayları</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .ticket-details { max-width: 600px; margin: 0 auto; }
                            h4, h5, h6 { color: #333; }
                            .text-primary { color: #6366f1 !important; }
                            .text-success { color: #10b981 !important; }
                            .text-warning { color: #f59e0b !important; }
                            .text-info { color: #06b6d4 !important; }
                            .text-white { color: #333 !important; }
                            .text-muted { color: #666 !important; }
                        </style>
                    </head>
                    <body>${content}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('tr-TR', {
                style: 'currency',
                currency: 'TRY'
            }).format(price);
        }
    </script>
</body>
</html>

