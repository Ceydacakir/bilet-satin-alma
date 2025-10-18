<?php
// Tek seferlik sefer ekleme scripti (23.10.2025)
session_start();
require_once 'config/database.php';

// Sadece admin çalıştırabilsin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo 'Yetkisiz.';
    exit;
}

$trips = [
    [1, 'İstanbul', 'Ankara', '2025-10-23 08:00:00', '2025-10-23 12:00:00', 350.00, 45],
    [1, 'Ankara', 'İstanbul', '2025-10-23 14:30:00', '2025-10-23 18:30:00', 350.00, 45],
    [2, 'İzmir', 'Ankara', '2025-10-23 09:00:00', '2025-10-23 15:00:00', 420.00, 45],
    [2, 'Ankara', 'İzmir', '2025-10-23 16:00:00', '2025-10-23 22:00:00', 420.00, 45],
    [3, 'Manisa', 'Adana', '2025-10-23 10:00:00', '2025-10-23 18:00:00', 390.00, 45],
    [3, 'Adana', 'Manisa', '2025-10-23 19:00:00', '2025-10-24 03:00:00', 390.00, 45]
];

$inserted = 0;
foreach ($trips as $trip) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE company_id = ? AND departure_city = ? AND destination_city = ? AND departure_time = ?");
    $stmt->execute([$trip[0], $trip[1], $trip[2], $trip[3]]);
    if ((int)$stmt->fetchColumn() === 0) {
        $stmt = $pdo->prepare("INSERT INTO trips (company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute($trip)) {
            $inserted++;
        }
    }
}

echo "Eklendi: $inserted sefer";



