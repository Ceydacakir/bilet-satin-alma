<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];

// Satılan biletleri getir
$stmt = $pdo->prepare("
    SELECT t.id, t.trip_id, t.user_id, t.status, t.total_price,
           tr.departure_city, tr.destination_city, tr.departure_time, tr.capacity,
           u.full_name as passenger_name
    FROM tickets t 
    JOIN trips tr ON t.trip_id = tr.id 
    JOIN users u ON t.user_id = u.id
    WHERE tr.company_id = ? 
    ORDER BY tr.departure_time DESC
");
$stmt->execute([$company_id]);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satılan Biletler - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <?php require_once '../includes/company_navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="text-white mb-4">
            <i class="fas fa-ticket-alt me-2"></i>Satılan Biletler
        </h2>

        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-white">Henüz Satılan Bilet Bulunmuyor</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Sefer</th>
                                    <th>Yolcu</th>
                                    <th>Koltuk No</th>
                                    <th>Tarih</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($ticket['departure_city']); ?></strong> - 
                                            <?php echo h($ticket['destination_city']); ?>
                                        </td>
                                        <td><?php echo h($ticket['passenger_name']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">-</span>
                                        </td>
                                        <td><?php echo formatDate($ticket['departure_time']); ?></td>
                                        <td><?php echo formatPrice($ticket['total_price']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = $ticket['status'] == 'active' ? 'success' : 'danger';
                                            $status_text = $ticket['status'] == 'active' ? 'Aktif' : 'İptal';
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
