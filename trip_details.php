<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - tüm roller sefer detaylarını görebilir
requireLogin();

$trip_id = $_GET['id'] ?? 0;

// Sefer bilgilerini al
$stmt = $pdo->prepare("
    SELECT t.*, bc.name as company_name, bc.logo_path,
           (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id = t.id AND tk.status = 'active') as sold_tickets
    FROM trips t
    JOIN bus_companies bc ON t.company_id = bc.id
    WHERE t.id = ?
");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch();

if (!$trip) {
    setErrorMessage('Sefer bulunamadı.');
    header('Location: search.php');
    exit();
}

// Dolu koltukları al
$booked_seats = getBookedSeats($pdo, $trip_id);
$available_seats = $trip['capacity'] - $trip['sold_tickets'];

$error_message = '';
$success_message = '';

// Bilet satın alma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_ticket'])) {
    // POST işleminde de bilet satın alma yetkisi kontrolü yap
    requireTicketPurchasePermission();
    $selected_seats_raw = $_POST['selected_seats'] ?? '';
    $selected_seats = !empty($selected_seats_raw) ? explode(',', $selected_seats_raw) : [];
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    
    if (empty($selected_seats) || !is_array($selected_seats)) {
        $error_message = 'Lütfen en az bir koltuk seçin.';
    } elseif (count($selected_seats) > 5) {
        $error_message = 'En fazla 5 koltuk seçebilirsiniz.';
    } else {
        // Koltuk müsaitlik kontrolü
        $invalid_seats = array_intersect($selected_seats, $booked_seats);
        if (!empty($invalid_seats)) {
            $error_message = 'Seçtiğiniz koltuklardan bazıları dolu. Lütfen tekrar seçin.';
        } else {
            // Kupon kontrolü
            $discount = 0;
            $coupon_id = null;
            if (!empty($coupon_code)) {
                $coupon_result = validateCoupon($pdo, $coupon_code, $_SESSION['user_id']);
                if ($coupon_result['valid']) {
                    $discount = $coupon_result['coupon']['discount'];
                    $coupon_id = $coupon_result['coupon']['id'];
                } else {
                    $error_message = $coupon_result['message'];
                }
            }
            
            if (empty($error_message)) {
                $total_price = $trip['price'] * count($selected_seats);
                $discounted_price = $total_price - ($total_price * $discount / 100);
                
                // Bakiye kontrolü
                if ($_SESSION['balance'] < $discounted_price) {
                    $error_message = 'Yetersiz bakiye. Lütfen hesabınıza para yükleyin.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // Bilet oluştur
                        $stmt = $pdo->prepare("
                            INSERT INTO tickets (trip_id, user_id, status, total_price) 
                            VALUES (?, ?, 'active', ?)
                        ");
                        $stmt->execute([$trip_id, $_SESSION['user_id'], $discounted_price]);
                        $ticket_id = $pdo->lastInsertId();
                        
                        // Koltukları rezerve et
                        foreach ($selected_seats as $seat) {
                            $stmt = $pdo->prepare("
                                INSERT INTO booked_seats (ticket_id, seat_number) 
                                VALUES (?, ?)
                            ");
                            $stmt->execute([$ticket_id, $seat]);
                        }
                        
                        // Kuponu kullan
                        if ($coupon_id) {
                            useCoupon($pdo, $coupon_id, $_SESSION['user_id']);
                        }
                        
                        // Bakiyeyi güncelle
                        updateUserBalance($pdo, $_SESSION['user_id'], -$discounted_price);
                        $_SESSION['balance'] -= $discounted_price;
                        
                        $pdo->commit();
                        
                        setSuccessMessage('Bilet başarıyla satın alındı!');
                        header('Location: tickets.php');
                        exit();
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = 'Bilet satın alma sırasında bir hata oluştu.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Detayları - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">Sefer Ara</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($_SESSION['role'] == 'user'): ?>
                                <li><a class="dropdown-item" href="profile.php">Hesabım</a></li>
                                <li><a class="dropdown-item" href="tickets.php">Biletlerim</a></li>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] == 'company'): ?>
                                <li><a class="dropdown-item" href="company/dashboard.php">Firma Paneli</a></li>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                                <li><a class="dropdown-item" href="admin/dashboard.php">Admin Paneli</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Geri Dön Butonu -->
        <div class="row mb-4">
            <div class="col-12">
                <a href="search.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                </a>
            </div>
        </div>

        <!-- Sefer Bilgileri -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <div class="company-logo mb-3">
                                    <i class="fas fa-bus fa-3x text-primary"></i>
                                </div>
                                <h5 class="text-white"><?php echo h($trip['company_name']); ?></h5>
                                <span class="badge bg-primary">Firma</span>
                                <div class="mt-2">
                                    <small class="text-muted">Sefer No: #<?php echo $trip['id']; ?></small>
                                </div>
                            </div>
                            
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="time-info text-center">
                                            <small class="text-muted">Kalkış</small>
                                            <h4 class="text-white"><?php echo formatDate($trip['departure_time'], 'H:i'); ?></h4>
                                            <p class="text-muted mb-0"><?php echo h($trip['departure_city']); ?></p>
                                            <small class="text-muted"><?php echo formatDate($trip['departure_time'], 'd.m.Y'); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 text-center">
                                        <div class="route-info">
                                            <i class="fas fa-arrow-right fa-2x text-primary"></i>
                                            <p class="text-muted mt-2">Yolculuk Süresi</p>
                                            <h6 class="text-white">
                                                <?php 
                                                $departure = new DateTime($trip['departure_time']);
                                                $arrival = new DateTime($trip['arrival_time']);
                                                $duration = $departure->diff($arrival);
                                                echo $duration->format('%h saat %i dakika');
                                                ?>
                                            </h6>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="time-info text-center">
                                            <small class="text-muted">Varış</small>
                                            <h4 class="text-white"><?php echo formatDate($trip['arrival_time'], 'H:i'); ?></h4>
                                            <p class="text-muted mb-0"><?php echo h($trip['destination_city']); ?></p>
                                            <small class="text-muted"><?php echo formatDate($trip['arrival_time'], 'd.m.Y'); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Koltuk Seçimi -->
                <?php if ($_SESSION['role'] === 'user'): ?>
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h5 class="text-white mb-4">
                            <i class="fas fa-chair me-2"></i>Koltuk Seçimi
                        </h5>
                        
                        <div class="seat-selection">
                            <div class="seat-grid">
                                <?php for ($i = 1; $i <= $trip['capacity']; $i++): ?>
                                    <?php
                                    $is_occupied = in_array($i, $booked_seats);
                                    $seat_class = $is_occupied ? 'occupied' : 'available';
                                    ?>
                                    <div class="seat <?php echo $seat_class; ?>" 
                                         data-seat="<?php echo $i; ?>"
                                         <?php echo $is_occupied ? 'title="Bu koltuk dolu"' : ''; ?>>
                                        <?php echo $i; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div class="seat-legend mt-4">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center">
                                            <div class="seat available me-2"></div>
                                            <span class="text-muted">Müsait</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center">
                                            <div class="seat selected me-2"></div>
                                            <span class="text-muted">Seçili</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center">
                                            <div class="seat occupied me-2"></div>
                                            <span class="text-muted">Dolu</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Ödeme Bilgileri -->
            <div class="col-lg-4">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h5 class="text-white mb-4">
                            <i class="fas fa-credit-card me-2"></i>Ödeme Bilgileri
                        </h5>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($_SESSION['role'] === 'user'): ?>
                        <form method="POST" id="purchaseForm">
                            <input type="hidden" name="purchase_ticket" value="1">
                            <input type="hidden" name="selected_seats" id="selectedSeats" value="">

                            <!-- Kupon Kodu -->
                            <div class="mb-3">
                                <label for="coupon_code" class="form-label">Kupon Kodu (Opsiyonel)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="coupon_code" name="coupon_code" 
                                           placeholder="Kupon kodunuzu girin">
                                    <button type="button" class="btn btn-outline-primary" id="applyCoupon">
                                        Uygula
                                    </button>
                                </div>
                                <div id="couponMessage"></div>
                            </div>

                            <!-- Seçilen Koltuklar -->
                            <div class="mb-3">
                                <label class="form-label">Seçilen Koltuklar</label>
                                <div id="selectedSeatsList" class="text-muted">
                                    Henüz koltuk seçilmedi
                                </div>
                            </div>

                            <!-- Fiyat Detayları -->
                            <div class="price-breakdown">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Koltuk Başına Fiyat:</span>
                                    <span class="text-white"><?php echo formatPrice($trip['price']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Seçilen Koltuk Sayısı:</span>
                                    <span class="text-white" id="seatCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Ara Toplam:</span>
                                    <span class="text-white" id="subtotal">0,00 ₺</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2" id="discountRow" style="display: none;">
                                    <span class="text-success">İndirim:</span>
                                    <span class="text-success" id="discountAmount">0,00 ₺</span>
                                </div>
                                <hr class="my-3">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-white">Toplam:</strong>
                                    <strong class="text-primary" id="totalPrice">0,00 ₺</strong>
                                </div>
                            </div>

                            <!-- Bakiye Bilgisi -->
                            <div class="balance-info mt-3 p-3 bg-secondary rounded">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Mevcut Bakiye:</span>
                                    <span class="text-white"><?php echo formatPrice($_SESSION['balance']); ?></span>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg" id="purchaseBtn" disabled>
                                    <i class="fas fa-ticket-alt me-2"></i>Bilet Satın Al
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Bilet Satın Alma Yetkisi Yok</strong><br>
                            <small>Bu işlem sadece yolcu kullanıcıları için geçerlidir.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        // Koltuk seçimi ve fiyat hesaplama
        let selectedSeats = [];
        let basePrice = <?php echo $trip['price']; ?>;
        let discount = 0;

        document.querySelectorAll('.seat.available').forEach(seat => {
            seat.addEventListener('click', function() {
                const seatNumber = parseInt(this.dataset.seat);
                
                if (this.classList.contains('selected')) {
                    // Koltuk seçimini kaldır
                    this.classList.remove('selected');
                    this.classList.add('available');
                    selectedSeats = selectedSeats.filter(s => s !== seatNumber);
                } else {
                    // Koltuk seç
                    if (selectedSeats.length >= 5) {
                        alert('En fazla 5 koltuk seçebilirsiniz.');
                        return;
                    }
                    this.classList.remove('available');
                    this.classList.add('selected');
                    selectedSeats.push(seatNumber);
                }
                
                updateSelectedSeats();
                updatePrice();
            });
        });

        function updateSelectedSeats() {
            const selectedSeatsInput = document.getElementById('selectedSeats');
            const selectedSeatsList = document.getElementById('selectedSeatsList');
            const purchaseBtn = document.getElementById('purchaseBtn');
            
            selectedSeatsInput.value = selectedSeats.join(',');
            
            if (selectedSeats.length > 0) {
                selectedSeatsList.innerHTML = selectedSeats.join(', ');
                purchaseBtn.disabled = false;
            } else {
                selectedSeatsList.innerHTML = 'Henüz koltuk seçilmedi';
                purchaseBtn.disabled = true;
            }
        }

        function updatePrice() {
            const seatCount = selectedSeats.length;
            const subtotal = basePrice * seatCount;
            const discountAmount = subtotal * discount / 100;
            const total = subtotal - discountAmount;
            
            document.getElementById('seatCount').textContent = seatCount;
            document.getElementById('subtotal').textContent = formatPrice(subtotal);
            document.getElementById('totalPrice').textContent = formatPrice(total);
            
            if (discount > 0) {
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountAmount').textContent = formatPrice(discountAmount);
            } else {
                document.getElementById('discountRow').style.display = 'none';
            }
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('tr-TR', {
                style: 'currency',
                currency: 'TRY'
            }).format(price);
        }

        // Kupon uygulama
        document.getElementById('applyCoupon').addEventListener('click', function() {
            const couponCode = document.getElementById('coupon_code').value.trim();
            if (couponCode) {
                // Burada AJAX ile kupon doğrulaması yapılacak
                // Şimdilik basit bir simülasyon
                if (couponCode === 'WELCOME10') {
                    discount = 10;
                    document.getElementById('couponMessage').innerHTML = 
                        '<div class="alert alert-success mt-2">Kupon başarıyla uygulandı!</div>';
                    updatePrice();
                } else {
                    document.getElementById('couponMessage').innerHTML = 
                        '<div class="alert alert-danger mt-2">Geçersiz kupon kodu!</div>';
                }
            }
        });
    </script>
</body>
</html>
