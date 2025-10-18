<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Giriş kontrolü - admin profil sayfasına erişemez
requireLogin();
if ($_SESSION['role'] === 'admin') {
    setErrorMessage('Admin kullanıcıları profil sayfasına erişemez.');
    header('Location: index.php');
    exit();
}

// Firma adminleri için user_id belirleme
$target_user_id = $_SESSION['user_id'];
if ($_SESSION['role'] === 'company') {
    // Firma admini kendi hesabını görüntülüyor
    $target_user_id = $_SESSION['user_id'];
}

$error_message = '';
$success_message = '';

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
                
                setSuccessMessage('Profil bilgileriniz başarıyla güncellendi.');
                header('Location: profile.php');
                exit();
                
            } catch (Exception $e) {
                $error_message = 'Profil güncellenirken bir hata oluştu.';
            }
        }
    }
}

// Bakiye yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_balance'])) {
    $amount = floatval($_POST['amount']);
    
    if ($amount <= 0) {
        $error_message = 'Geçerli bir miktar girin.';
    } elseif ($amount > 10000) {
        $error_message = 'Tek seferde en fazla 10.000 TL yükleyebilirsiniz.';
    } else {
        try {
            updateUserBalance($pdo, $_SESSION['user_id'], $amount);
            $_SESSION['balance'] += $amount;
            setSuccessMessage('Bakiye başarıyla yüklendi.');
            header('Location: profile.php');
            exit();
        } catch (Exception $e) {
            $error_message = 'Bakiye yüklenirken bir hata oluştu.';
        }
    }
}

// Kullanıcı bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch();

// İstatistikleri al
$stats = [];

// Toplam bilet sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
$stmt->execute([$target_user_id]);
$stats['total_tickets'] = $stmt->fetchColumn();

// Aktif bilet sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'active'");
$stmt->execute([$target_user_id]);
$stats['active_tickets'] = $stmt->fetchColumn();

// Toplam harcama
$stmt = $pdo->prepare("SELECT SUM(total_price) FROM tickets WHERE user_id = ? AND status = 'active'");
$stmt->execute([$target_user_id]);
$stats['total_spent'] = $stmt->fetchColumn() ?: 0;

// Son biletleri al (en son 5 bilet)
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
    LIMIT 5
");
$stmt->execute([$target_user_id]);
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
                            <li><a class="dropdown-item active" href="profile.php">Hesabım</a></li>
                            <li><a class="dropdown-item" href="tickets.php">Biletlerim</a></li>
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
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-white">
                    <i class="fas fa-user-circle me-2"></i>Hesabım 🚀
                </h2>
                <p class="text-light">Profil bilgilerinizi yönetin ve hesap istatistiklerinizi görüntüleyin.</p>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <div class="row">
            <!-- Sol Kolon - Profil Bilgileri -->
            <div class="col-lg-8">
                <!-- Profil Düzenleme -->
                <div class="card mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: 1px solid #475569;">
                    <div class="card-header" style="background: rgba(99, 102, 241, 0.1); border-bottom: 1px solid #6366f1;">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-edit me-2"></i>Profil Bilgileri 🚀
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label text-white">Ad Soyad</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo h($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label text-white">E-posta</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo h($user['email']); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="current_password" class="form-label text-white">Mevcut Şifre</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" 
                                           placeholder="Şifre değiştirmek için girin">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="new_password" class="form-label text-white">Yeni Şifre</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Yeni şifre">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label text-white">Şifre Tekrar</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Yeni şifre tekrar">
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Güncelle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Son Biletler -->
                <div class="card mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: 1px solid #475569;">
                    <div class="card-header" style="background: rgba(99, 102, 241, 0.1); border-bottom: 1px solid #6366f1;">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-ticket-alt me-2"></i>Son Biletlerim 🎫
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_tickets)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-ticket-alt fa-2x text-muted mb-2"></i>
                                <p class="text-light mb-0">Henüz biletiniz yok</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_tickets as $ticket): ?>
                                    <div class="list-group-item" style="background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; margin-bottom: 0.5rem; border-radius: 0.5rem;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-white mb-1">
                                                    <?php echo h($ticket['departure_city']); ?> → <?php echo h($ticket['destination_city']); ?>
                                                </h6>
                                                <small class="text-light">
                                                    <i class="fas fa-calendar me-1"></i><?php echo formatDate($ticket['departure_time'], 'd.m.Y H:i'); ?>
                                                    <span class="ms-2"><i class="fas fa-chair me-1"></i><?php echo h($ticket['seat_numbers']); ?></span>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <?php
                                                $badge_class = $ticket['status'] === 'active' ? 'bg-success' : ($ticket['status'] === 'cancelled' ? 'bg-danger' : 'bg-secondary');
                                                $badge_text = $ticket['status'] === 'active' ? 'Aktif' : ($ticket['status'] === 'cancelled' ? 'İptal' : 'Süresi Dolmuş');
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?> mb-2"><?php echo $badge_text; ?></span>
                                                <div class="text-white fw-bold"><?php echo formatPrice($ticket['total_price']); ?></div>
                                                <?php if ($ticket['status'] == 'active'): ?>
                                                    <a href="download_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                                                        <i class="fas fa-download me-1"></i>PDF
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="tickets.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>Tüm Biletleri Gör
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bakiye Yükleme -->
                <div class="card" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: 1px solid #475569;">
                    <div class="card-header" style="background: rgba(99, 102, 241, 0.1); border-bottom: 1px solid #6366f1;">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-wallet me-2"></i>Bakiye Yükle 💰
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="add_balance" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="amount" class="form-label text-white">Miktar (TL)</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           min="10" max="10000" step="10" placeholder="Yüklenecek miktar" required>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-plus me-2"></i>Bakiye Yükle
                                    </button>
                                </div>
                            </div>
                            
                            <small class="text-light">
                                <i class="fas fa-info-circle me-1"></i>
                                Minimum 10 TL, maksimum 10.000 TL yükleyebilirsiniz.
                            </small>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon - İstatistikler ve Bilgiler -->
            <div class="col-lg-4">
                <!-- Hesap Bilgileri -->
                <div class="card mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: 1px solid #475569;">
                    <div class="card-header" style="background: rgba(99, 102, 241, 0.1); border-bottom: 1px solid #6366f1;">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-info-circle me-2"></i>Hesap Bilgileri 🚀
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-light">Kullanıcı ID</small>
                            <p class="text-white mb-0">#<?php echo $user['id']; ?></p>
                        </div>
                        <div class="mb-3">
                            <small class="text-light">Rol</small>
                            <p class="text-white mb-0">
                                <?php
                                $role_names = [
                                    'user' => 'Yolcu',
                                    'company' => 'Firma Admin',
                                    'admin' => 'Sistem Admin'
                                ];
                                echo $role_names[$user['role']] ?? $user['role'];
                                ?>
                            </p>
                        </div>
                        <div class="mb-3">
                            <small class="text-light">Kayıt Tarihi</small>
                            <p class="text-white mb-0"><?php echo formatDate($user['created_at'], 'd.m.Y'); ?></p>
                        </div>
                        <div class="mb-0">
                            <small class="text-light">Bilet Kredisi 💳</small>
                            <h4 class="text-primary mb-0"><?php echo formatPrice($user['balance']); ?></h4>
                        </div>
                    </div>
                </div>

                <!-- İstatistikler -->
                <div class="card mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: 1px solid #475569;">
                    <div class="card-header" style="background: rgba(99, 102, 241, 0.1); border-bottom: 1px solid #6366f1;">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-chart-bar me-2"></i>İstatistikler 📊
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary mb-1"><?php echo $stats['total_tickets']; ?> 🎫</h4>
                                <small class="text-light">Toplam Bilet</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-success mb-1"><?php echo $stats['active_tickets']; ?> ✅</h4>
                                <small class="text-light">Aktif Bilet</small>
                            </div>
                            <div class="col-12">
                                <h4 class="text-warning mb-1"><?php echo formatPrice($stats['total_spent']); ?> 💰</h4>
                                <small class="text-light">Toplam Harcama</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hızlı Erişim -->
                <div class="card" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: 1px solid #475569;">
                    <div class="card-header" style="background: rgba(99, 102, 241, 0.1); border-bottom: 1px solid #6366f1;">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-bolt me-2"></i>Hızlı Erişim ⚡
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
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                                <a href="admin/dashboard.php" class="btn btn-outline-danger">
                                    <i class="fas fa-cog me-2"></i>Admin Paneli
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
    </script>
</body>
</html>

