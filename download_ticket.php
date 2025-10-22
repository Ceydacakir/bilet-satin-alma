<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - tüm kullanıcılar PDF indirebilir
requireLogin();

$ticket_id = $_GET['id'] ?? 0;

// Bilet bilgilerini al - rol bazlı sorgu
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
        WHERE t.id = ? AND t.user_id = ? AND t.status = 'active'
        GROUP BY t.id
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
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
        WHERE t.id = ? AND tr.company_id = ? AND t.status = 'active'
        GROUP BY t.id
    ");
    $stmt->execute([$ticket_id, $_SESSION['company_id']]);
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
        WHERE t.id = ? AND t.status = 'active'
        GROUP BY t.id
    ");
    $stmt->execute([$ticket_id]);
}
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
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8fafc; }
        .ticket { border: 3px solid #6366f1; padding: 30px; max-width: 600px; background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .company { font-size: 28px; font-weight: bold; color: #6366f1; margin-bottom: 10px; }
        .route { font-size: 22px; margin: 15px 0; color: #1e293b; font-weight: 600; }
        .details { display: flex; justify-content: space-between; margin: 30px 0; flex-wrap: wrap; }
        .detail-item { text-align: center; margin: 10px; flex: 1; min-width: 120px; }
        .detail-label { font-size: 14px; color: #64748b; margin-bottom: 5px; font-weight: 500; }
        .detail-value { font-size: 18px; font-weight: bold; color: #1e293b; }
        .footer { text-align: center; margin-top: 30px; font-size: 14px; color: #64748b; border-top: 2px solid #e2e8f0; padding-top: 20px; }
        .rocket { color: #6366f1; margin-right: 5px; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="company">🚀 HopBilet</div>
            <div class="route">' . h($ticket['departure_city']) . ' → ' . h($ticket['destination_city']) . '</div>
        </div>
        
        <div class="details">
            <div class="detail-item">
                <div class="detail-label">🚀 Kalkış</div>
                <div class="detail-value">' . formatDate($ticket['departure_time'], 'H:i') . '</div>
                <div class="detail-value">' . formatDate($ticket['departure_time'], 'd.m.Y') . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🚀 Varış</div>
                <div class="detail-value">' . formatDate($ticket['arrival_time'], 'H:i') . '</div>
                <div class="detail-value">' . formatDate($ticket['arrival_time'], 'd.m.Y') . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🚀 Koltuk</div>
                <div class="detail-value">' . h($ticket['seat_numbers']) . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🚀 Fiyat</div>
                <div class="detail-value">' . formatPrice($ticket['total_price']) . '</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Bilet No: #' . $ticket['id'] . '</p>
            <p>Yolcu: ' . h($ticket['passenger_name']) . '</p>
            <p>Firma: ' . h($ticket['company_name']) . '</p>
            <p>Satın Alma: ' . formatDate($ticket['created_at'], 'd.m.Y H:i') . '</p>
            ' . ($_SESSION['role'] !== 'user' ? '<p>E-posta: ' . h($ticket['passenger_email']) . '</p>' : '') . '
        </div>
    </div>
</body>
</html>';

// PDF oluşturma (basit HTML çıktısı)
header('Content-Type: text/html; charset=UTF-8');
echo $html;
?>

