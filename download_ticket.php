<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - sadece user PDF indirebilir
requireLogin();
if ($_SESSION['role'] !== 'user') {
    setErrorMessage('PDF bilet indirme işlemi sadece yolcu kullanıcıları için geçerlidir.');
    header('Location: index.php');
    exit();
}

$ticket_id = $_GET['id'] ?? 0;

// Bilet bilgilerini al
$stmt = $pdo->prepare("
    SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
           bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers
    FROM tickets t
    JOIN trips tr ON t.trip_id = tr.id
    JOIN bus_companies bc ON tr.company_id = bc.id
    LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
    WHERE t.id = ? AND t.user_id = ? AND t.status = 'active'
    GROUP BY t.id
");
$stmt->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setErrorMessage('Bilet bulunamadı.');
    header('Location: tickets.php');
    exit();
}

// Basit HTML bilet oluştur (PDF için)
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bilet - HopBilet</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .ticket { border: 2px solid #333; padding: 20px; max-width: 600px; }
        .header { text-align: center; margin-bottom: 20px; }
        .company { font-size: 24px; font-weight: bold; color: #6366f1; }
        .route { font-size: 20px; margin: 10px 0; }
        .details { display: flex; justify-content: space-between; margin: 20px 0; }
        .detail-item { text-align: center; }
        .detail-label { font-size: 12px; color: #666; }
        .detail-value { font-size: 16px; font-weight: bold; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="company">HopBilet</div>
            <div class="route">' . h($ticket['departure_city']) . ' → ' . h($ticket['destination_city']) . '</div>
        </div>
        
        <div class="details">
            <div class="detail-item">
                <div class="detail-label">Kalkış</div>
                <div class="detail-value">' . formatDate($ticket['departure_time'], 'H:i') . '</div>
                <div class="detail-value">' . formatDate($ticket['departure_time'], 'd.m.Y') . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Varış</div>
                <div class="detail-value">' . formatDate($ticket['arrival_time'], 'H:i') . '</div>
                <div class="detail-value">' . formatDate($ticket['arrival_time'], 'd.m.Y') . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Koltuk</div>
                <div class="detail-value">' . h($ticket['seat_numbers']) . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Fiyat</div>
                <div class="detail-value">' . formatPrice($ticket['total_price']) . '</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Bilet No: #' . $ticket['id'] . '</p>
            <p>Yolcu: ' . h($_SESSION['full_name']) . '</p>
            <p>Firma: ' . h($ticket['company_name']) . '</p>
            <p>Satın Alma: ' . formatDate($ticket['created_at'], 'd.m.Y H:i') . '</p>
        </div>
    </div>
</body>
</html>';

// PDF oluşturma (basit HTML çıktısı)
header('Content-Type: text/html; charset=UTF-8');
echo $html;
?>

