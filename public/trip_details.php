<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user = getUserData($pdo, $_SESSION['user_id']);

$trip_id = $_GET['id'] ?? 0;

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

// Seferin zamanı geçmiş mi kontrol et
$is_trip_expired = strtotime($trip['departure_time']) <= time();

// Kullanılabilir kuponları al
$available_coupons = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT c.code, c.discount
        FROM coupons c
        LEFT JOIN user_coupons uc ON c.id = uc.coupon_id AND uc.user_id = :user_id
        WHERE 
            (c.company_id = :company_id OR c.company_id IS NULL)
            AND (c.expire_date IS NULL OR c.expire_date >= DATE('now'))
            AND uc.id IS NULL
            AND (
                SELECT COUNT(*) FROM user_coupons WHERE coupon_id = c.id
            ) < c.usage_limit
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id'], 'company_id' => $trip['company_id']]);
    $available_coupons = $stmt->fetchAll();
}


$booked_seats = getBookedSeats($pdo, $trip_id);
$available_seats = $trip['capacity'] - $trip['sold_tickets'];

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_ticket'])) {
    requireTicketPurchasePermission();
    $selected_seats_raw = $_POST['selected_seats'] ?? '';
    $selected_seats = !empty($selected_seats_raw) ? array_map('intval', explode(',', $selected_seats_raw)) : [];
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    
    if ($is_trip_expired) {
        $error_message = 'Bu seferin kalkış saati geçtiği için bilet satın alamazsınız.';
    } elseif (empty($selected_seats) || !is_array($selected_seats)) {
        $error_message = 'Lütfen en az bir koltuk seçin.';
    } elseif (count($selected_seats) !== count(array_unique($selected_seats))) {
        $error_message = 'Aynı koltuğu birden fazla seçemezsiniz.';
    } elseif (count($selected_seats) > 5) {
        $error_message = 'En fazla 5 koltuk seçebilirsiniz.';
    } else {
        $unique_selected_seats = array_unique($selected_seats);

        $invalid_seats = array_intersect($selected_seats, $booked_seats);
        if (!empty($invalid_seats)) {
            $error_message = 'Seçtiğiniz koltuklardan bazıları dolu. Lütfen tekrar seçin.';
        } else {
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
                
                if ($user['balance'] < $discounted_price) {
                    $error_message = 'Yetersiz bakiye. Lütfen hesabınıza para yükleyin.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        $ticket_id = uniqid('ticket_');
                        $stmt = $pdo->prepare("
                            INSERT INTO tickets (id, trip_id, user_id, status, total_price) 
                            VALUES (?, ?, ?, 'active', ?)
                        ");
                        $stmt->execute([$ticket_id, $trip_id, $_SESSION['user_id'], $discounted_price]);

                        
                        foreach ($selected_seats as $seat) {
                            $seat_id = uniqid('seat_');
                            $stmt = $pdo->prepare("
                                INSERT INTO booked_seats (id,ticket_id, seat_number) 
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$seat_id, $ticket_id, $seat]);
                        }
                        
                        if ($coupon_id) {
                            useCoupon($pdo, $coupon_id, $_SESSION['user_id']);
                        }
                        
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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <a href="search.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Geri Dön
            </a>
        </div>
    </div>
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
            <?php if ($_SESSION['role'] === 'user' && !$is_trip_expired): ?>
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
                                     title="<?php echo $is_occupied ? 'Bu koltuk dolu' : 'Müsait'; ?>"><?php echo $i; ?>
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
                    <?php if ($_SESSION['role'] === 'user' && !$is_trip_expired): ?>
                    <form method="POST" id="purchaseForm">
                        <input type="hidden" name="purchase_ticket" value="1">
                        <input type="hidden" name="selected_seats" id="selectedSeats" value="">
                        <?php if (!empty($available_coupons)): ?>
                        <div class="mb-3">
                            <label class="form-label">Kullanabileceğiniz Kuponlar</label>
                            <div class="available-coupons">
                                <?php foreach ($available_coupons as $coupon): ?>
                                    <button type="button" class="btn btn-outline-success btn-sm coupon-tag" data-code="<?php echo h($coupon['code']); ?>" data-discount="<?php echo h($coupon['discount']); ?>">
                                        <strong><?php echo h($coupon['code']); ?></strong> - %<?php echo h($coupon['discount']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="coupon_code" class="form-label">Kupon Kodu (Opsiyonel)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="coupon_code" name="coupon_code" 
                                       placeholder="Kupon kodunuzu girin">
                                <button type="button" class="btn btn-outline-primary" id="applyCoupon">
                                    Uygula
                                </button>
                            </div>
                            <div id="couponMessage" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Seçilen Koltuklar</label>
                            <div id="selectedSeatsList" class="text-muted">
                                Henüz koltuk seçilmedi
                            </div>
                        </div>
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
                        <div class="balance-info mt-3 p-3 bg-secondary rounded">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Mevcut Bakiye:</span>
                                <span class="text-white"><?php echo formatPrice($user['balance']); ?></span>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="purchaseBtn" disabled>
                                <i class="fas fa-ticket-alt me-2"></i>Bilet Satın Al
                            </button>
                        </div>
                    </form>
                    <?php elseif ($is_trip_expired): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Sefer Zamanı Geçti</strong><br>
                        <small>Bu sefer için bilet satışı kapanmıştır.</small>
                    </div>
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
document.addEventListener('DOMContentLoaded', () => {

    const seats = document.querySelectorAll('.seat');
    let selectedSeats = [];
    const maxSeats = 5;
    let basePrice = <?php echo $trip['price']; ?>;
    let discount = 0;

    seats.forEach(seat => {
        seat.addEventListener('click', () => {
            // Dolu koltuğa tıklanamaz
            if (seat.classList.contains('occupied')) return;

            const seatNumber = parseInt(seat.dataset.seat);

            if (seat.classList.contains('selected')) {
                seat.classList.remove('selected');
                seat.classList.add('available');
                selectedSeats = selectedSeats.filter(num => num !== seatNumber);
            } else {
                if (selectedSeats.length >= maxSeats) {
                    updatePrice();
                    updateSelectedSeats();
                    alert(`En fazla ${maxSeats} koltuk seçebilirsiniz.`);
                    return;
                }
                seat.classList.remove('available');
                seat.classList.add('selected');
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
    function applyCoupon(code, discountValue) {
        discount = discountValue;
        document.getElementById('coupon_code').value = code;
        document.getElementById('couponMessage').innerHTML = 
            `<div class="alert alert-success p-2"><strong>%${discount} indirim</strong> uygulandı!</div>`;
        updatePrice();
    }
    document.querySelectorAll('.coupon-tag').forEach(tag => {
        tag.addEventListener('click', function() {
            const code = this.dataset.code;
            const discountValue = parseFloat(this.dataset.discount);
            applyCoupon(code, discountValue);
        });
    });
    document.getElementById('applyCoupon').addEventListener('click', function() {
        const couponCode = document.getElementById('coupon_code').value.trim();
        if (couponCode) {
            // Şimdilik, manuel girişte basit bir mesaj gösteriyoruz.
            // Gerçek doğrulama sunucu tarafında yapılıyor.
            document.getElementById('couponMessage').innerHTML = 
                `<div class="alert alert-info p-2">Kupon, satın alma sırasında kontrol edilecek.</div>`;
        }
    });

    
});

document.querySelectorAll('.seat.available').forEach(seat => {
    seat.addEventListener('click', function() {
        const seatNumber = parseInt(this.dataset.seat);
        
        if (this.classList.contains('selected')) {
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
        
    });
});

</script>
</body>
</html>
