<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Giriş kontrolü - tüm yolcular ve firma sahibi bileti PDF indirebilir
requireAnyRole(['user','company']);

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

// PDF oluşturmak için mPDF kütüphanesini dahil et
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    // Kütüphane bulunamazsa, kullanıcı dostu bir hata mesajı göster ve işlemi durdur.
    // Bu, sunucuda "composer install" komutunun çalıştırılması gerektiğini belirtir.
    die('PDF oluşturma kütüphanesi bulunamadı. Lütfen sistem yöneticinizle iletişime geçin ve Composer bağımlılıklarının yüklendiğinden emin olun.');
}
require_once $autoloader;

// PDF için HTML içeriği oluştur
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bilet - HopBilet</title>
    <style>
        body { font-family: "dejavu sans", sans-serif; color: #374151; background-color: #f3f4f6; }
        .ticket-container { padding: 12px; }
        .ticket { border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; background: #ffffff; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }
        .header { padding-bottom: 12px; margin-bottom: 12px; border-bottom: 2px dashed #d1d5db; }
        .company-name { font-size: 24px; font-weight: bold; color: #6d28d9; text-align: left; }
        .route { font-size: 22px; font-weight: bold; margin-top: 5px; text-align: right; color: #1f2937; }
        .details-table { width: 100%; margin-top: 8px; border-collapse: collapse; }
        .details-table td { padding: 8px; border-bottom: 1px solid #f3f4f6; }
        .detail-label { font-size: 13px; color: #6b7280; }
        .detail-value { font-size: 16px; font-weight: bold; color: #111827; }
        .passenger-info { margin-top: 12px; padding: 14px; background: #f3f4f6; border-radius: 8px; border-left: 5px solid #6d28d9; }
        .footer { text-align: center; margin-top: 12px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="ticket">
            <div class="header">
                <table width="100%">
                    <tr>
                        <td class="company-name">HopBilet</td>
                        <td class="route">' . h($ticket['departure_city']) . ' → ' . h($ticket['destination_city']) . '</td>
                    </tr>
                </table>
            </div>
            
            <table class="details-table">
                <tr>
                    <td>
                        <div class="detail-label">Firma</div>
                        <div class="detail-value">' . h($ticket['company_name']) . '</div>
                    </td>
                    <td>
                        <div class="detail-label">Koltuk No</div>
                        <div class="detail-value">' . h($ticket['seat_numbers']) . '</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="detail-label">Kalkış</div>
                        <div class="detail-value">' . formatDate($ticket['departure_time'], 'd.m.Y H:i') . '</div>
                    </td>
                    <td>
                        <div class="detail-label">Varış</div>
                        <div class="detail-value">' . formatDate($ticket['arrival_time'], 'd.m.Y H:i') . '</div>
                    </td>
                </tr>
            </table>

            <div class="passenger-info">
                <strong>Yolcu:</strong> ' . h($ticket['passenger_name']) . '<br>
                <strong>Bilet No:</strong> #' . $ticket['id'] . '<br>
                <strong>Fiyat:</strong> ' . formatPrice($ticket['total_price']) . '
            </div>
            
            <div class="footer">
                <p>Satın Alma Tarihi: ' . formatDate($ticket['created_at'], 'd.m.Y H:i') . '</p>
                <p>İyi yolculuklar dileriz!</p>
            </div>
        </div>
    </div>
</body>
</html>';

try {
    // mPDF için yazılabilir bir geçici klasör belirle
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => sys_get_temp_dir() . '/mpdf',
        'mode' => 'utf-8', 
        'format' => 'A5-L'
    ]);
    $mpdf->SetTitle('HopBilet - Bilet');
    $mpdf->WriteHTML($html);
    $mpdf->Output('HopBilet-Bilet-' . $ticket['id'] . '.pdf', 'D'); // 'D' -> Tarayıcıda indirmeyi zorlar
} catch (\Mpdf\MpdfException $e) {
    die('PDF oluşturulurken bir hata oluştu: ' . $e->getMessage());
}
?>
