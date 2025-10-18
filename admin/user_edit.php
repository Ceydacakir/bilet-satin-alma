<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$error_message = '';
$user = null;

// Kullanıcı ID kontrolü
if (!isset($_GET['id'])) {
    setErrorMessage('Kullanıcı ID belirtilmedi.');
    header('Location: companies.php');
    exit();
}

$user_id = (int)$_GET['id'];

// Kullanıcıyı al
$stmt = $pdo->prepare("
    SELECT u.*, bc.name as company_name 
    FROM users u 
    LEFT JOIN bus_companies bc ON u.company_id = bc.id 
    WHERE u.id = ? AND u.role = 'company'
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    setErrorMessage('Kullanıcı bulunamadı veya firma admin kullanıcısı değil.');
    header('Location: companies.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $balance = (float)($_POST['balance'] ?? 0);
    
    // Validasyon
    if (empty($full_name) || strlen($full_name) < 2) {
        $error_message = 'Ad soyad en az 2 karakter olmalı.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalı.';
    } elseif ($balance < 0) {
        $error_message = 'Bakiye negatif olamaz.';
    } else {
        // E-posta benzersizliği kontrolü (kendi ID'si hariç)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->fetch()) {
            $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            try {
                if (!empty($password)) {
                    // Şifre güncelleme ile
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, balance = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $hashed_password, $balance, $user_id]);
                } else {
                    // Şifre güncelleme olmadan
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, balance = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $balance, $user_id]);
                }
                
                setSuccessMessage('Kullanıcı başarıyla güncellendi.');
                header('Location: company_users.php?id=' . $user['company_id']);
                exit();
                
            } catch (Exception $e) {
                $error_message = 'Kullanıcı güncellenirken bir hata oluştu.';
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
    <title>Kullanıcı Düzenle - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Admin Paneli</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="companies.php">Firmalar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="coupons.php">Kuponlar</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php">Hesabım</a></li>
                            <li><a class="dropdown-item" href="../tickets.php">Biletlerim</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Çıkış Yap</a></li>
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="text-white">
                            <i class="fas fa-user-edit me-2"></i>Kullanıcı Düzenle
                        </h2>
                        <p class="text-muted">
                            <strong><?php echo h($user['company_name']); ?></strong> firması admin kullanıcısını düzenleyin
                        </p>
                    </div>
                    <a href="company_users.php?id=<?php echo $user['company_id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Ad Soyad</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo h($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo h($user['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="password" class="form-label">Yeni Şifre (opsiyonel)</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       minlength="6" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Şifreyi değiştirmek istemiyorsanız boş bırakın
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="balance" class="form-label">Bakiye (₺)</label>
                                <input type="number" class="form-control" id="balance" name="balance" 
                                       value="<?php echo number_format($user['balance'], 2, '.', ''); ?>" 
                                       step="0.01" min="0">
                            </div>
                            
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Firma:</strong> <?php echo h($user['company_name']); ?><br>
                                    <strong>Rol:</strong> Firma Admin<br>
                                    <strong>Kayıt Tarihi:</strong> <?php echo formatDate($user['created_at']); ?>
                                </div>
                            </div>

                            <div class="col-12 text-end">
                                <a href="company_users.php?id=<?php echo $user['company_id']; ?>" class="btn btn-secondary me-2">İptal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Güncelle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>