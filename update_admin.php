<?php
require_once 'config/database.php';

// Güncellenecek bilgiler
$adminId = 1;
$newData = [
    'full_name' => 'Admin User', // Yeni ad
    'email' => 'admin@hopbilet.com', // Yeni email
    'password' => 'admin123', // Yeni şifre
    'balance' => 10000.00 // Yeni bakiye
];

try {
    // Şifreyi hash'le
    $hashedPassword = password_hash($newData['password'], PASSWORD_DEFAULT);
    
    // Admin kullanıcısını güncelle
    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, email = ?, password = ?, balance = ? 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $newData['full_name'],
        $newData['email'], 
        $hashedPassword,
        $newData['balance'],
        $adminId
    ]);
    
    if ($result) {
        echo "✅ Admin kullanıcısı başarıyla güncellendi!\n\n";
        
        // Güncellenmiş bilgileri göster
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$adminId]);
        $user = $stmt->fetch();
        
        echo "📋 Güncel Bilgiler:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Ad: " . $user['full_name'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Rol: " . $user['role'] . "\n";
        echo "Bakiye: " . number_format($user['balance'], 2) . " ₺\n";
        echo "Şifre: " . $newData['password'] . "\n";
        
    } else {
        echo "❌ Hata: Admin kullanıcısı güncellenemedi.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Veritabanı hatası: " . $e->getMessage() . "\n";
}
?>
