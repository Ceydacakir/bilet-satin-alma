<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - user, admin ve company PDF indirebilir
requireLogin();

$is_admin_view = false;
if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'company') {
    $is_admin_view = true;
}

$ticket_id = $_GET['id'] ?? 0;

// Bilet bilgilerini al
if ($is_admin_view) {
    if ($_SESSION['role'] === 'admin') {
        // Admin tüm biletleri indirebilir
        $stmt = $pdo->prepare("
            SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
                   bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers,
                   u.full_name as passenger_name, u.email as passenger_email
            FROM tickets t
            JOIN trips tr ON t.trip_id = tr.id
            JOIN bus_companies bc ON tr.company_id = bc.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
            WHERE t.id = ? AND t.status = 'active'
            GROUP BY t.id
        ");
        $stmt->execute([$ticket_id]);
    } else {
        // Company admin sadece kendi firma biletlerini indirebilir
        $stmt = $pdo->prepare("
            SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
                   bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers,
                   u.full_name as passenger_name, u.email as passenger_email
            FROM tickets t
            JOIN trips tr ON t.trip_id = tr.id
            JOIN bus_companies bc ON tr.company_id = bc.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
            WHERE t.id = ? AND bc.id = ? AND t.status = 'active'
            GROUP BY t.id
        ");
        $stmt->execute([$ticket_id, $_SESSION['company_id']]);
    }
} else {
    // Normal user sadece kendi biletlerini indirebilir
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
}
$ticket = $stmt->fetch();

if (!$ticket) {
    setErrorMessage('Bilet bulunamadı.');
    header('Location: tickets.php');
    exit();
}

// Gelişmiş HTML bilet oluştur (PDF için)
$passenger_name = $is_admin_view && isset($ticket['passenger_name']) ? $ticket['passenger_name'] : $_SESSION['full_name'];

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bilet - HopBilet</title>
    <style>
        body { 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .ticket { 
            background: white;
            border-radius: 15px; 
            padding: 30px; 
            max-width: 650px; 
            margin: 0 auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        .ticket::before {
            content: "🚀";
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 3rem;
            opacity: 0.1;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid #6366f1;
            padding-bottom: 20px;
        }
        .company { 
            font-size: 32px; 
            font-weight: bold; 
            color: #6366f1; 
            margin-bottom: 5px;
        }
        .company-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        .route { 
            font-size: 24px; 
            margin: 15px 0; 
            color: #333;
            font-weight: 600;
        }
        .ticket-number {
            background: #6366f1;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
        }
        .details { 
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0; 
        }
        .detail-item { 
            text-align: center; 
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #6366f1;
        }
        .detail-label { 
            font-size: 12px; 
            color: #666; 
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .detail-value { 
            font-size: 18px; 
            font-weight: bold; 
            color: #333;
            margin-bottom: 5px;
        }
        .price-highlight {
            color: #10b981;
            font-size: 24px;
        }
        .seat-highlight {
            color: #f59e0b;
            font-size: 22px;
        }
        .passenger-info {
            background: #e0e7ff;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .passenger-info h3 {
            color: #6366f1;
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        .footer { 
            text-align: center; 
            margin-top: 30px; 
            font-size: 12px; 
            color: #666; 
            border-top: 2px dashed #ddd;
            padding-top: 20px;
        }
        .footer p {
            margin: 5px 0;
        }
        .qr-placeholder {
            width: 80px;
            height: 80px;
            background: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px auto;
            font-size: 10px;
            color: #64748b;
        }
        .rocket-divider {
            text-align: center;
            margin: 20px 0;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="company">🚀 HopBilet</div>
            <div class="company-subtitle">Uzayda Hızlı Seyahat</div>
            <div class="route">' . h($ticket['departure_city']) . ' → ' . h($ticket['destination_city']) . '</div>
            <div class="ticket-number">Bilet #' . $ticket['id'] . '</div>
        </div>
        
        <div class="passenger-info">
            <h3>👤 Yolcu Bilgileri</h3>
            <p><strong>Ad Soyad:</strong> ' . h($passenger_name) . '</p>
            ' . ($is_admin_view && isset($ticket['passenger_email']) ? '<p><strong>E-posta:</strong> ' . h($ticket['passenger_email']) . '</p>' : '') . '
        </div>
        
        <div class="details">
            <div class="detail-item">
                <div class="detail-label">🛫 Kalkış</div>
                <div class="detail-value">' . formatDate($ticket['departure_time'], 'H:i') . '</div>
                <div class="detail-value">' . formatDate($ticket['departure_time'], 'd.m.Y') . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">🛬 Varış</div>
                <div class="detail-value">' . formatDate($ticket['arrival_time'], 'H:i') . '</div>
                <div class="detail-value">' . formatDate($ticket['arrival_time'], 'd.m.Y') . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">💺 Koltuk</div>
                <div class="detail-value seat-highlight">' . h($ticket['seat_numbers']) . '</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">💰 Fiyat</div>
                <div class="detail-value price-highlight">' . formatPrice($ticket['total_price']) . '</div>
            </div>
        </div>
        
        <div class="rocket-divider">🚀 ✨ 🚀 ✨ 🚀</div>
        
        <div class="qr-placeholder">
            QR Kod<br>Alanı
        </div>
        
        <div class="footer">
            <p><strong>Firma:</strong> ' . h($ticket['company_name']) . '</p>
            <p><strong>Satın Alma Tarihi:</strong> ' . formatDate($ticket['created_at'], 'd.m.Y H:i') . '</p>
            <p><strong>İyi Yolculuklar! 🚀</strong></p>
            <p style="margin-top: 15px; font-size: 10px;">
                Bu bilet HopBilet sistemi tarafından elektronik olarak oluşturulmuştur.<br>
                Seyahat sırasında yanınızda bulundurmanız gerekmektedir.
            </p>
        </div>
    </div>
</body>
</html>';

// PDF oluşturma (basit HTML çıktısı)
header('Content-Type: text/html; charset=UTF-8');
echo $html;
?>

