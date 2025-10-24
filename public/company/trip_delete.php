<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

global $pdo; 
$company_id = $_SESSION['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trip_id'])) {
    $trip_id = $_POST['trip_id'];
    
    $result = cancelTripByCompany($pdo, $trip_id, $company_id);
    
    if ($result['success']) {
        setSuccessMessage($result['message']);
    } else {
        setErrorMessage($result['message']);
    }

} else {
    setErrorMessage('Geçersiz istek.');
}

header('Location: trips.php');
exit();
?>