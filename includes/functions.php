<?php
// Yardımcı fonksiyonlar

// Kullanıcı giriş yapmış mı kontrol et
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Kullanıcı rolünü kontrol et
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Kullanıcının belirli bir role sahip olup olmadığını kontrol et
function hasAnyRole($roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

// Yetkisiz erişimi engelle
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Bilet satın alma yetkisi kontrolü - sadece user rolü
function requireTicketPurchasePermission() {
    requireLogin();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
        if ($_SESSION['role'] === 'admin') {
            setErrorMessage('Admin kullanıcıları bilet satın alamaz.');
        } elseif ($_SESSION['role'] === 'company') {
            setErrorMessage('Firma admin kullanıcıları bilet satın alamaz.');
        } else {
            setErrorMessage('Bilet satın alma işlemi sadece yolcu kullanıcıları için geçerlidir.');
        }
        header('Location: index.php');
        exit();
    }
}

// Belirli rol gerektiren sayfalar için
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: index.php');
        exit();
    }
}

// Birden fazla rol gerektiren sayfalar için
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        header('Location: index.php');
        exit();
    }
}

// Güvenli çıktı için HTML escape
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Başarı mesajı göster
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

// Hata mesajı göster
function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

// Mesajları göster ve temizle
function displayMessages() {
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo h($_SESSION['success_message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo h($_SESSION['error_message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['error_message']);
    }
}

// Tarih formatla
function formatDate($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

// Para formatla
function formatPrice($price) {
    // SQLite'tan NULL gelebilir; PHP 8.1+'de null number_format depreceated uyarısı verir
    $normalizedPrice = is_numeric($price) ? (float)$price : 0.0;
    return number_format($normalizedPrice, 2, ',', '.') . ' ₺';
}

// Kullanıcı bakiyesini güncelle
function updateUserBalance($pdo, $user_id, $amount) {
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    return $stmt->execute([$amount, $user_id]);
}

// Kullanıcı bakiyesini al
function getUserBalance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['balance'] : 0;
}

// Kupon kodunu kontrol et
function validateCoupon($pdo, $code, $user_id = null) {
    $stmt = $pdo->prepare("
        SELECT c.*, bc.name as company_name 
        FROM coupons c 
        LEFT JOIN bus_companies bc ON c.company_id = bc.id 
        WHERE c.code = ? AND (c.expire_date IS NULL OR c.expire_date >= DATE('now'))
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        return ['valid' => false, 'message' => 'Geçersiz kupon kodu'];
    }
    
    // Kullanım limitini kontrol et
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_coupons WHERE coupon_id = ?");
    $stmt->execute([$coupon['id']]);
    $usageCount = $stmt->fetchColumn();
    
    if ($usageCount >= $coupon['usage_limit']) {
        return ['valid' => false, 'message' => 'Kupon kullanım limiti dolmuş'];
    }
    
    // Kullanıcı daha önce bu kuponu kullanmış mı?
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_coupons WHERE coupon_id = ? AND user_id = ?");
        $stmt->execute([$coupon['id'], $user_id]);
        $userUsage = $stmt->fetchColumn();
        
        if ($userUsage > 0) {
            return ['valid' => false, 'message' => 'Bu kuponu daha önce kullandınız'];
        }
    }
    
    return ['valid' => true, 'coupon' => $coupon];
}

// Kuponu kullan
function useCoupon($pdo, $coupon_id, $user_id) {
    $stmt = $pdo->prepare("INSERT INTO user_coupons (coupon_id, user_id) VALUES (?, ?)");
    return $stmt->execute([$coupon_id, $user_id]);
}

// Sefer için dolu koltukları al
function getBookedSeats($pdo, $trip_id) {
    $stmt = $pdo->prepare("
        SELECT bs.seat_number 
        FROM booked_seats bs 
        JOIN tickets t ON bs.ticket_id = t.id 
        WHERE t.trip_id = ? AND t.status = 'active'
    ");
    $stmt->execute([$trip_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Bilet iptal edilebilir mi?
function canCancelTicket($departureTime) {
    date_default_timezone_set('Europe/Istanbul');
    $now = new DateTime();
    $departure = new DateTime($departureTime);
    
    // Calculate difference in hours
    $diff = $departure->getTimestamp() - $now->getTimestamp();
    $hoursRemaining = $diff / 3600;
    
    // Allow cancellation only if more than 1 hour remains
    return $hoursRemaining > 1;
}

// PDF bilet oluştur
function generateTicketPDF($ticket_data) {
    // Bu fonksiyon daha sonra PDF kütüphanesi ile implement edilecek
    return "PDF oluşturuldu: Bilet #" . $ticket_data['id'];
}

// Güvenli dosya yükleme
function uploadFile($file, $upload_dir = 'uploads/') {
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Geçersiz dosya türü'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Dosya boyutu çok büyük'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Dosya yüklenemedi'];
}
?>
