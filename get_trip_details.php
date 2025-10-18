<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü
requireLogin();

header('Content-Type: application/json');

$trip_id = $_GET['trip_id'] ?? 0;

try {
    // Sefer bilgilerini al
    $stmt = $pdo->prepare("
        SELECT t.*, bc.name as company_name
        FROM trips t
        JOIN bus_companies bc ON t.company_id = bc.id
        WHERE t.id = ?
    ");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch();

    if (!$trip) {
        echo json_encode(['success' => false, 'message' => 'Sefer bulunamadı']);
        exit();
    }

    // Firma kullanıcısı ise kendi seferlerini görebilir
    if ($_SESSION['role'] === 'company' && $trip['company_id'] != $_SESSION['company_id']) {
        echo json_encode(['success' => false, 'message' => 'Bu sefere erişim yetkiniz yok']);
        exit();
    }

    // Bu seferdeki tüm dolu koltukları al
    $booked_seats = getBookedSeats($pdo, $trip_id);

    echo json_encode([
        'success' => true,
        'trip' => $trip,
        'booked_seats' => $booked_seats
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Veri alınırken hata oluştu: ' . $e->getMessage()]);
}
?>