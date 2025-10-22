<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Validate session and input
if (!isset($_SESSION['user_id']) || !isset($_POST['ticket_id'])) {
    setErrorMessage('Geçersiz istek.');
    header('Location: tickets.php');
    exit();
}

$ticket_id = (int)$_POST['ticket_id'];

// Get ticket information
$stmt = $pdo->prepare("
    SELECT t.*, tr.departure_time, tr.departure_city, tr.destination_city
    FROM tickets t 
    JOIN trips tr ON t.trip_id = tr.id 
    WHERE t.id = ? AND t.user_id = ? AND t.status = 'active'
");

$stmt->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: tickets.php');
    exit();
}

// Check cancellation eligibility
if (!canCancelTicket($ticket['departure_time'])) {
    setErrorMessage('Kalkışa 1 saatten az kaldığı için bilet iptal edilemez.');
    header('Location: tickets.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Update ticket status - simplified query
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$ticket_id]);
    
    // Update user balance - using simple query
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$ticket['total_price'], $_SESSION['user_id']]);
    
    $pdo->commit();
    setSuccessMessage('Biletiniz başarıyla iptal edildi.');
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage()); // Log the actual error
    setErrorMessage('Bilet iptal edilemedi.');
}

header('Location: tickets.php');
exit();
