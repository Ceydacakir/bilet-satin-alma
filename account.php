<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü
requireLogin();

// Admin kullanıcıları hesap sayfasına erişemez
if ($_SESSION['role'] === 'admin') {
    setErrorMessage('Admin kullanıcıları hesap sayfasına erişemez.');
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

// Kredi yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_credits'])) {
    $amount = floatval($_POST['amount']);
    
    if ($amount <= 0) {
        $error_message = 'Geçerli bir miktar girin.';
    } elseif ($amount > 10000) {
        $error_message = 'Tek seferde en fazla 10.000 TL kredi yükleyebilirsiniz.';
    } else {
        try {
            updateUserBalance($pdo, $_SESSION['user_id'], $amount);
            $_SESSION['balance'] += $amount;
            setSuccessMessage('Kredi başarıyla yüklendi. 🚀');
            header('Location: account.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'Kredi yüklenirken bir hata oluştu.';
        }
    }
}

// Profil güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasyon
    if (empty($full_name) || empty($email)) {
        $error_message = 'Ad soyad ve e-posta alanları zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } else {
        // E-posta değişikliği kontrolü
        if ($email !== $_SESSION['email']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
            }
        }
        
        // Şifre değişikliği kontrolü
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $error_message = 'Mevcut şifrenizi girin.';
            } elseif (!password_verify($current_password, $_SESSION['password_hash'] ?? '')) {
                $error_message = 'Mevcut şifre hatalı.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'Yeni şifreler eşleşmiyor.';
            } elseif (strlen($new_password) < 6) {
                $error_message = 'Yeni şifre en az 6 karakter olmalıdır.';
            }
        }
        
        if (empty($error_message)) {
            try {
                if (!empty($new_password)) {
                    // Şifre ile birlikte güncelle
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $hashed_password, $_SESSION['user_id']]);
                } else {
                    // Sadece profil bilgilerini güncelle
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
                }
                
                // Session bilgilerini güncelle
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                setSuccessMessage('Profil bilgileriniz başarıyla güncellendi. 🚀');
                header('Location: account.php');
                exit();
                
            } catch (Exception $e) {
                $error_message = 'Profil güncellenirken bir hata oluştu.';
            }
        }
    }
}

// Kullanıcı bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// İstatistikleri al
$stats = [];

// Toplam bilet sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_tickets'] = $stmt->fetchColumn();

// Aktif bilet sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$stats['active_tickets'] = $stmt->fetchColumn();

// Toplam harcama
$stmt = $pdo->prepare("SELECT SUM(total_price) FROM tickets WHERE user_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_spent'] = $stmt->fetchColumn() ?: 0;

// Kredi geçmişi (son 10 işlem)
$stmt = $pdo->prepare("
    SELECT 'ticket_purchase' as type, total_price as amount, created_at, 
           CONCAT(tr.departure_city, ' → ', tr.destination_city) as description
    FROM tickets t
    JOIN trips tr ON t.trip_id = tr.id
    WHERE t.user_id = ?
    UNION ALL
    SELECT 'credit_load' as type, 0 as amount, created_at, 'Kredi Yükleme' as description
    FROM users 
    WHERE id = ? AND created_at IS NOT NULL
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$credit_history = $stmt->fetchAll();

// Son biletler
$stmt = $pdo->prepare("
    SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
           bc.name as company_name, GROUP_CONCAT(bs.seat_number) as seat_numbers
    FROM tickets t
    JOIN trips tr ON t.trip_id = tr.id
    JOIN bus_companies bc ON tr.company_id = bc.id
    LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
    WHERE t.user_id = ?
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$recent_tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabım - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Background Elements -->
    <div class="rockets"></div>
    <div class="space-particles"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-rocket me-2 rocket-icon"></i>HopBilet
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
                            <li><a class="dropdown-item active" href="account.php">Hesabım</a></li>
                            <li><a class="dropdown-item" href="tickets.php">Biletlerim</a></li>
                            <?php if ($_SESSION['role'] == 'company'): ?>
                                <li><a class="dropdown-item" href="company/dashboard.php">Firma Paneli</a></li>
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
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-white">
                    <i class="fas fa-rocket me-2 rocket-icon"></i>Hesabım
                </h2>
                <p class="text-muted">Hesap bilgilerinizi yönetin ve kredi durumunuzu görüntüleyin</p>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Sol Kolon - Kredi ve İstatistikler -->
            <div class="col-lg-4 mb-4">
                <!-- Kredi Kartı -->
                <div class="card credits-card mb-4">
                    <div class="card-body text-center">
                        <h6 class="mb-1 opacity-75">Mevcut Kredi</h6>
                        <h2 class="mb-3"><?php echo formatPrice($_SESSION['balance']); ?></h2>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#creditModal">
                            <i class="fas fa-plus me-2"></i>Kredi Yükle
                        </button>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-chart-line me-2"></i>İstatistikler
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4 mb-3">
                                <h4 class="text-primary mb-1"><?php echo $stats['total_tickets']; ?></h4>
                                <small class="text-muted">Toplam</small>
                            </div>
                            <div class="col-4 mb-3">
                                <h4 class="text-success mb-1"><?php echo $stats['active_tickets']; ?></h4>
                                <small class="text-muted">Aktif</small>
                            </div>
                            <div class="col-4 mb-3">
                                <h4 class="text-warning mb-1"><?php echo formatPrice($stats['total_spent']); ?></h4>
                                <small class="text-muted">Harcama</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hesap Bilgileri -->
                <div class="card bg-dark border-secondary">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-info-circle me-2"></i>Hesap Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Kullanıcı ID</small>
                            <p class="text-white mb-0">#<?php echo $user['id']; ?></p>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Rol</small>
                            <p class="text-white mb-0">
                                <?php
                                $role_names = [
                                    'user' => 'Yolcu 🚀',
                                    'company' => 'Firma Admin 🏢',
                                    'admin' => 'Sistem Admin ⚙️'
                                ];
                                echo $role_names[$user['role']] ?? $user['role'];
                                ?>
                            </p>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted">Kayıt Tarihi</small>
                            <p class="text-white mb-0"><?php echo formatDate($user['created_at'], 'd.m.Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orta Kolon - Profil Düzenleme -->
            <div class="col-lg-5 mb-4">
                <div class="card bg-dark border-secondary">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-user-edit me-2"></i>Profil Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="full_name" class="form-label">Ad Soyad</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo h($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="email" class="form-label">E-posta</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo h($user['email']); ?>" required>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="text-white mb-3">Şifre Değiştir</h6>

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mevcut Şifre</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" 
                                       placeholder="Şifre değiştirmek için girin">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">Yeni Şifre</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Yeni şifre">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Şifre Tekrar</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Yeni şifre tekrar">
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-rocket me-2"></i>Güncelle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon - Son Biletler -->
            <div class="col-lg-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-ticket-alt me-2"></i>Son Biletler
                        </h5>
                        <a href="tickets.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-1"></i>Tümü
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_tickets)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-rocket fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Henüz bilet yok</p>
                                <a href="search.php" class="btn btn-outline-primary btn-sm mt-2">
                                    <i class="fas fa-search me-1"></i>Sefer Ara
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_tickets as $ticket): ?>
                                <div class="ticket-card mb-3 p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="text-white mb-0">
                                            <?php echo h($ticket['departure_city']); ?> → <?php echo h($ticket['destination_city']); ?>
                                        </h6>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($ticket['status']) {
                                            case 'active':
                                                $status_class = 'bg-success';
                                                $status_text = 'Aktif';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-danger';
                                                $status_text = 'İptal';
                                                break;
                                            case 'expired':
                                                $status_class = 'bg-secondary';
                                                $status_text = 'Süresi Dolmuş';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?> badge-sm"><?php echo $status_text; ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i><?php echo formatDate($ticket['departure_time'], 'd.m.Y H:i'); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-chair me-1"></i>Koltuk: <?php echo h($ticket['seat_numbers']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hızlı Erişim -->
                <div class="card bg-dark border-secondary mt-4">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-rocket me-2"></i>Hızlı Erişim
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="tickets.php" class="btn btn-outline-primary">
                                <i class="fas fa-ticket-alt me-2"></i>Biletlerim
                            </a>
                            <a href="search.php" class="btn btn-outline-success">
                                <i class="fas fa-search me-2"></i>Sefer Ara
                            </a>
                            <?php if ($_SESSION['role'] == 'company'): ?>
                                <a href="company/dashboard.php" class="btn btn-outline-warning">
                                    <i class="fas fa-building me-2"></i>Firma Paneli
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kredi Yükleme Modal -->
    <div class="modal fade" id="creditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-rocket me-2"></i>Kredi Yükle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_credits" value="1">
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Miktar (TL)</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   min="10" max="10000" step="10" placeholder="Yüklenecek miktar" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 col-md-3 mb-2">
                                <button type="button" class="btn btn-outline-primary w-100 credit-btn" data-amount="50">50 TL</button>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <button type="button" class="btn btn-outline-primary w-100 credit-btn" data-amount="100">100 TL</button>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <button type="button" class="btn btn-outline-primary w-100 credit-btn" data-amount="250">250 TL</button>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <button type="button" class="btn btn-outline-primary w-100 credit-btn" data-amount="500">500 TL</button>
                            </div>
                        </div>
                        
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Minimum 10 TL, maksimum 10.000 TL yükleyebilirsiniz.
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-rocket me-2"></i>Kredi Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        // Şifre değişikliği validasyonu
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });

        // Kredi miktarı seçimi
        document.querySelectorAll('.credit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const amount = this.dataset.amount;
                document.getElementById('amount').value = amount;
            });
        });
    </script>
</body>
</html>