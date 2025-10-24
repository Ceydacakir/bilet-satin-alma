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

function getUserData($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
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
    if (empty($date) || strtotime($date) === false) {
        return ''; // veya 'Tarih Yok' gibi bir varsayılan değer
    }
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
    
    if ($coupon['usage_limit'] > 0 && $usageCount >= $coupon['usage_limit']) {
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

// Seferin tamamlanıp tamamlanmadığını kontrol eden fonksiyon
function isTripCompleted($pdo, $trip_id) {
    $stmt = $pdo->prepare("SELECT arrival_time FROM trips WHERE id = ?");
    $stmt->execute([$trip_id]);
    $arrival_time = $stmt->fetchColumn();

    if (!$arrival_time) {
        return false; // sefer yoksa tamamlanmış sayılmaz
    }
    $now = new DateTime();
    $arrival = new DateTime($arrival_time);

    return $arrival < $now; // varış zamanı geçmişse sefer tamamlanmıştır
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

function cancelTicketByUser($pdo, $ticket_id, $user_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, tr.departure_time 
        FROM tickets t 
        JOIN trips tr ON t.trip_id = tr.id 
        WHERE t.id = ? AND t.user_id = ? AND t.status = 'active'
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        return ['success' => false, 'message' => 'Bilet bulunamadı veya daha önce iptal edilmiş.'];
    }
    
    if (!canCancelTicket($ticket['departure_time'])) {
        return ['success' => false, 'message' => 'Kalkış saatinden 1 saatten az kaldığı için bilet iptal edilemez.'];
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$ticket_id]);
        
        $stmt = $pdo->prepare("DELETE FROM booked_seats WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        
        $refund_amount = $ticket['total_price'];
        updateUserBalance($pdo, $user_id, $refund_amount); 
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Bilet başarıyla iptal edildi. Ücret bakiyenize iade edildi.'];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Bilet iptal edilirken bir veritabanı hatası oluştu.'];
    }
}

function cancelTicketByCompany($pdo, $ticket_id, $company_id) {
    // 1. Bilet ve Sefer Bilgilerini Al ve Firma Yetkisini Kontrol Et
    $stmt = $pdo->prepare("
        SELECT t.*, tr.company_id AS trip_company_id 
        FROM tickets t 
        JOIN trips tr ON t.trip_id = tr.id 
        WHERE t.id = ? AND t.status = 'active'
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        return ['success' => false, 'message' => 'Aktif bir bilet bulunamadı.'];
    }

    // Firma Yetki Kontrolü: Biletin bağlı olduğu sefer, işlemi yapan firmaya mı ait?
    if ($ticket['trip_company_id'] != $company_id) {
        return ['success' => false, 'message' => 'Bu bilet, firmanıza ait değildir ve iptal edemezsiniz.'];
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$ticket_id]);
        
        $stmt = $pdo->prepare("DELETE FROM booked_seats WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        
        $refund_amount = $ticket['total_price'];
        $user_id = $ticket['user_id'];
        
        updateUserBalance($pdo, $user_id, $refund_amount); 
        
        $pdo->commit();
        
        $message = "Bilet başarıyla iptal edildi. " . number_format($refund_amount, 2) . " TL tutar, kullanıcının bakiyesine iade edildi.";
        return ['success' => true, 'message' => $message];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Bilet iptal edilirken bir hata oluştu. Lütfen tekrar deneyin.'];
    }
}

function cancelTripByCompany($pdo, $trip_id, $company_id) {
    // 1. Sefer Bilgilerini Al ve Firma Yetkisini Kontrol Et
    $stmt = $pdo->prepare("SELECT id, company_id FROM trips WHERE id = ?");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        return ['success' => false, 'message' => 'Silinecek bir sefer bulunamadı.'];
    }

    if ($trip['company_id'] != $company_id) {
        return ['success' => false, 'message' => 'Bu sefer firmanıza ait değildir ve silinemez.'];
    }

    // 2. Sefer tamamlanmış mı kontrol et
    $trip_completed = isTripCompleted($pdo, $trip_id);

    try {
        $pdo->beginTransaction();

        $total_refund_amount = 0;
        $cancelled_ticket_count = 0;

        if (!$trip_completed) {
            // 🟢 Henüz tamamlanmamışsa aktif biletleri bul
            $stmt = $pdo->prepare("
                SELECT id, user_id, total_price 
                FROM tickets 
                WHERE trip_id = ? AND status = 'active'
            ");
            $stmt->execute([$trip_id]);
            $active_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Her bilet için iade yap
            foreach ($active_tickets as $ticket) {
                $user_id = $ticket['user_id'];
                $refund_amount = (float)$ticket['total_price'];
                updateUserBalance($pdo, $user_id, $refund_amount);
                $total_refund_amount += $refund_amount;
                $cancelled_ticket_count++;
            }

            // Biletleri iptal et
            if ($cancelled_ticket_count > 0) {
                $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE trip_id = ? AND status = 'active'");
                $stmt->execute([$trip_id]);
            }

        } else {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'expired' WHERE trip_id = ? AND status = 'active'");
            $stmt->execute([$trip_id]);
        }

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

        if ($trip_completed) {
            $message = "Sefer tamamlanmış olduğu için bilet iadesi yapılmadı. Sefer başarıyla silindi.";
        } else {
            $message = "Sefer başarıyla iptal edildi. Toplam " . 
                        $cancelled_ticket_count . " adet bilet iptal edilerek, " . 
                        number_format($total_refund_amount, 2) . " TL tutar kullanıcılara iade edildi.";
        }

        return [
            'success' => true, 
            'message' => $message,
            'total_refund' => $total_refund_amount
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Sefer iptal edilirken bir hata oluştu. Lütfen tekrar deneyin.'];
    }
}

function getTripOccupancy($pdo, $trip_id) {
    // Sefer kapasitesini al
    $stmt = $pdo->prepare("SELECT capacity FROM trips WHERE id = ?");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        return ['occupied' => 0, 'capacity' => 0, 'percentage' => 0, 'available' => 0];
    }

    $capacity = (int)$trip['capacity'];

    // Dolu koltuk sayısını booked_seats üzerinden al
    $stmt = $pdo->prepare("SELECT COUNT(*) as occupied_seats FROM booked_seats bs
                           JOIN tickets t ON bs.ticket_id = t.id
                           WHERE t.trip_id = ? AND t.status = 'active'");
    $stmt->execute([$trip_id]);
    $occupied = (int)$stmt->fetchColumn();

    $available = $capacity - $occupied;
    $percentage = $capacity > 0 ? ($occupied / $capacity) * 100 : 0;

    return [
        'occupied' => $occupied,
        'capacity' => $capacity,
        'available' => $available,
        'percentage' => round($percentage, 1)
    ];
}


?>
