<?php
session_start();

// Oturumu sonlandır
session_destroy();

// Ana sayfaya yönlendir
header('Location: index.php');
exit();
?>

