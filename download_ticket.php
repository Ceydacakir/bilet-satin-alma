<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - user ve company rolleri PDF indirebilir
requireLogin();
if ($_SESSION['role'] === 'admin') {
    setErrorMessage('Admin kullanıcıları PDF bilet indiremez.');
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
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .ticket { border: 3px solid #6366f1; padding: 30px; max-width: 650px; background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 15px; margin: 20px auto; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #6366f1; padding-bottom: 15px; }
        .company { font-size: 28px; font-weight: bold; color: #6366f1; }
        .route { font-size: 22px; margin: 10px 0; color: #333; font-weight: 600; }
        .details { display: flex; justify-content: space-between; margin: 25px 0; padding: 20px; background: linear-gradient(135deg, #f0f0f0 0%, #e0e0e0 100%); border-radius: 10px; }
        .detail-item { text-align: center; padding: 10px; }
        .detail-label { font-size: 13px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-value { font-size: 18px; font-weight: bold; color: #333; margin-top: 5px; }
        .footer { text-align: center; margin-top: 25px; font-size: 13px; color: #666; padding-top: 20px; border-top: 2px solid #e0e0e0; }
        .footer p { margin: 8px 0; }
        .rocket { font-size: 20px; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="company">🚀 HopBilet 🚀</div>
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
            <p><strong>Bilet No:</strong> #' . $ticket['id'] . ' 🎫</p>
            <p><strong>Yolcu:</strong> ' . h($_SESSION['full_name']) . '</p>
            <p><strong>Firma:</strong> ' . h($ticket['company_name']) . '</p>
            <p><strong>Satın Alma:</strong> ' . formatDate($ticket['created_at'], 'd.m.Y H:i') . '</p>
            <p class="rocket">✨ İyi Yolculuklar! 🚀</p>
        </div>
    </div>
</body>
</html>';

// PDF oluşturma (basit HTML çıktısı)
header('Content-Type: text/html; charset=UTF-8');
echo $html;
?>

