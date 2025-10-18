<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trip_id'])) {
    $trip_id = (int)$_POST['trip_id'];
    
    // Seferin bu firmaya ait olduğunu kontrol et
    $stmt = $pdo->prepare("SELECT id FROM trips WHERE id = ? AND company_id = ?");
    $stmt->execute([$trip_id, $company_id]);
    
    if ($stmt->fetch()) {
        try {
            $pdo->beginTransaction();
            
            // Rezerve koltukları sil
            $stmt = $pdo->prepare("
                DELETE FROM booked_seats WHERE ticket_id IN (
                    SELECT id FROM tickets WHERE trip_id = ?
                )
            ");
            $stmt->execute([$trip_id]);
            
            // Biletleri sil
            $stmt = $pdo->prepare("DELETE FROM tickets WHERE trip_id = ?");
            $stmt->execute([$trip_id]);
            
            // Seferi sil
            $stmt = $pdo->prepare("DELETE FROM trips WHERE id = ?");
            $stmt->execute([$trip_id]);
            
            $pdo->commit();
            setSuccessMessage('Sefer başarıyla silindi.');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setErrorMessage('Sefer silinirken bir hata oluştu.');
        }
    } else {
        setErrorMessage('Sefer bulunamadı veya silinemez.');
    }
}

header('Location: trips.php');
exit();
?>

