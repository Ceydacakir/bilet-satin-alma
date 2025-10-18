<?php
require_once 'config/database.php';

echo "🔧 Admin Kullanıcısını Güncelleme Aracı\n";
echo "=====================================\n\n";

echo "Mevcut admin kullanıcısı:\n";
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = 1');
$stmt->execute();
$currentUser = $stmt->fetch();

if ($currentUser) {
    echo "ID: " . $currentUser['id'] . "\n";
    echo "Ad: " . $currentUser['full_name'] . "\n";
    echo "Email: " . $currentUser['email'] . "\n";
    echo "Rol: " . $currentUser['role'] . "\n";
    echo "Bakiye: " . number_format($currentUser['balance'], 2) . " ₺\n\n";
    
    echo "Güncellemek istediğiniz bilgileri girin:\n";
    echo "=====================================\n";
    
    // Yeni bilgileri al
    $newName = readline("Yeni ad (mevcut: " . $currentUser['full_name'] . "): ");
    $newEmail = readline("Yeni email (mevcut: " . $currentUser['email'] . "): ");
    $newPassword = readline("Yeni şifre (mevcut: admin123): ");
    $newBalance = readline("Yeni bakiye (mevcut: " . $currentUser['balance'] . "): ");
    
    // Boş değerleri mevcut değerlerle değiştir
    $updateName = !empty($newName) ? $newName : $currentUser['full_name'];
    $updateEmail = !empty($newEmail) ? $newEmail : $currentUser['email'];
    $updatePassword = !empty($newPassword) ? password_hash($newPassword, PASSWORD_DEFAULT) : $currentUser['password'];
    $updateBalance = !empty($newBalance) ? floatval($newBalance) : $currentUser['balance'];
    
    // Güncelle
    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, email = ?, password = ?, balance = ? 
        WHERE id = 1
    ");
    
    $result = $stmt->execute([$updateName, $updateEmail, $updatePassword, $updateBalance]);
    
    if ($result) {
        echo "\n✅ Admin kullanıcısı başarıyla güncellendi!\n";
        
        // Güncellenmiş bilgileri göster
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = 1');
        $stmt->execute();
        $updatedUser = $stmt->fetch();
        
        echo "\n📋 Güncel Bilgiler:\n";
        echo "ID: " . $updatedUser['id'] . "\n";
        echo "Ad: " . $updatedUser['full_name'] . "\n";
        echo "Email: " . $updatedUser['email'] . "\n";
        echo "Rol: " . $updatedUser['role'] . "\n";
        echo "Bakiye: " . number_format($updatedUser['balance'], 2) . " ₺\n";
        if (!empty($newPassword)) {
            echo "Şifre: " . $newPassword . "\n";
        }
    } else {
        echo "\n❌ Hata: Güncelleme başarısız!\n";
    }
    
} else {
    echo "❌ Admin kullanıcısı bulunamadı!\n";
}
?>
