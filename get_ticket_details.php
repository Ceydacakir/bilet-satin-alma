<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü
requireLogin();

header('Content-Type: application/json');

$ticket_id = $_GET['ticket_id'] ?? 0;
$trip_id = $_GET['trip_id'] ?? 0;

try {
    // Bilet bilgilerini al
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
               bc.name as company_name, GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number) as seat_numbers
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_companies bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        WHERE t.id = ? AND t.user_id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Bilet bulunamadı']);
        exit();
    }

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

    // Bu seferdeki tüm dolu koltukları al
    $booked_seats = getBookedSeats($pdo, $trip_id);

    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'trip' => $trip,
        'booked_seats' => $booked_seats
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Veri alınırken hata oluştu: ' . $e->getMessage()]);
}
?>