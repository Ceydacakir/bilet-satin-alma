<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
if ($_SESSION['role'] === 'admin') {
    setErrorMessage('Admin kullanıcıları profil sayfasına erişemez.');
    header('Location: index.php');
    exit();
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

$user = getUserData($pdo, $_SESSION['user_id']);
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

require_once __DIR__ . '/../includes/header.php';
?>

    <div class="container mt-4">
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-white">
                    <i class="fas fa-rocket me-2"></i>Hesabım
                </h2>
                <p class="text-muted">Profil bilgilerinizi yönetin ve hesap istatistiklerinizi görüntüleyin.</p>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <div class="row">
            <!-- Sol Kolon - Profil Bilgileri -->
            <div class="col-lg-8">
                <!-- Profil Düzenleme -->
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-rocket me-2"></i>Profil Bilgileri
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
                                    <label for="full_name" class="form-label">Ad Soyad</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo h($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">E-posta</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo h($user['email']); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="current_password" class="form-label">Mevcut Şifre</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" 
                                           placeholder="Şifre değiştirmek için girin">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="new_password" class="form-label">Yeni Şifre</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Yeni şifre">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Şifre Tekrar</label>
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

                <!-- Bakiye Yükleme Sadece Yolcu -->
                 <?php if ($_SESSION['role'] == 'user'): ?>
                    <div class="card bg-dark border-secondary">
                        <div class="card-header">
                            <h5 class="text-white mb-0">
                                <i class="fas fa-rocket me-2"></i>Bakiye Yükle
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="add_balance" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="amount" class="form-label">Miktar (TL)</label>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                            min="10" max="10000" step="10" placeholder="Yüklenecek miktar" required>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-plus me-2"></i>Bakiye Yükle
                                        </button>
                                    </div>
                                </div>
                                
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Minimum 10 TL, maksimum 10.000 TL yükleyebilirsiniz.
                                </small>
                            </form>
                        </div>
                    </div>
                 <?php endif; ?>
                
            </div>

            <!-- Sağ Kolon - İstatistikler ve Bilgiler -->
            <div class="col-lg-4">
                <!-- Hesap Bilgileri -->
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-rocket me-2"></i>Hesap Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Rol</small>
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
                            <small class="text-muted">Kayıt Tarihi</small>
                            <p class="text-white mb-0"><?php echo formatDate($user['created_at'], 'd.m.Y H:m'); ?></p>
                        </div>
                        <?php if ($_SESSION['role'] == 'user'): ?>
                            <div class="mb-0">
                                <small class="text-muted">
                                    <i class="fas fa-rocket me-1"></i>Mevcut Bakiye
                                </small>
                                <h4 class="text-primary mb-0"><?php echo formatPrice($user['balance']); ?></h4>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- İstatistikler -->
                 <?php if ($_SESSION['role'] == 'user'): ?>
                    <div class="card bg-dark border-secondary mb-4">
                        <div class="card-header">
                            <h5 class="text-white mb-0">
                                <i class="fas fa-rocket me-2"></i>İstatistikler
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h4 class="text-primary mb-1"><?php echo $stats['total_tickets']; ?></h4>
                                    <small class="text-muted">
                                        <i class="fas fa-rocket me-1"></i>Toplam Bilet
                                    </small>
                                </div>
                                <div class="col-6 mb-3">
                                    <h4 class="text-success mb-1"><?php echo $stats['active_tickets']; ?></h4>
                                    <small class="text-muted">
                                        <i class="fas fa-rocket me-1"></i>Aktif Bilet
                                    </small>
                                </div>
                                <div class="col-12">
                                    <h4 class="text-warning mb-1"><?php echo formatPrice($stats['total_spent']); ?></h4>
                                    <small class="text-muted">
                                        <i class="fas fa-rocket me-1"></i>Toplam Harcama
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Hızlı Erişim -->
                <div class="card bg-dark border-secondary">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-rocket me-2"></i>Hızlı Erişim
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($_SESSION['role'] == 'user'): ?>
                                <a href="tickets.php" class="btn btn-outline-primary">
                                    <i class="fas fa-rocket me-2"></i>Biletlerim
                                </a>
                            <?php endif; ?>
                            <a href="search.php" class="btn btn-outline-success">
                                <i class="fas fa-search me-2"></i>Sefer Ara
                            </a>
                            <?php if ($_SESSION['role'] == 'company'): ?>
                                <a href="company/dashboard.php" class="btn btn-outline-warning">
                                    <i class="fas fa-rocket me-2"></i>Firma Paneli
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

