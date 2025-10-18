<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    
    // Validasyon
    if (empty($name)) {
        $error_message = 'Firma adı boş olamaz.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error_message = 'Firma adı 2-100 karakter arasında olmalıdır.';
    } else {
        // Firma adının benzersizliğini kontrol et
        $stmt = $pdo->prepare("SELECT id FROM bus_companies WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->fetch()) {
            $error_message = 'Bu firma adı zaten kullanılıyor.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO bus_companies (name, logo_path) VALUES (?, ?)");
                $stmt->execute([$name, '']); // Logo yolu boş
                
                setSuccessMessage('Firma başarıyla oluşturuldu.');
                header('Location: companies.php');
                exit();
                
            } catch (Exception $e) {
                $error_message = 'Firma oluşturulurken bir hata oluştu.';
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
    <title>Yeni Firma Ekle - HopBilet</title>
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
                            <i class="fas fa-plus me-2"></i>Yeni Firma Ekle
                        </h2>
                        <p class="text-muted">Sisteme yeni bir otobüs firması ekleyin</p>
                    </div>
                    <a href="companies.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="name" class="form-label">Firma Adı</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="Örn: RoketOtobüs" value="<?php echo h($_POST['name'] ?? ''); ?>" 
                                       maxlength="100" required>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    2-100 karakter arasında, benzersiz olmalı
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="companies.php" class="btn btn-secondary me-2">İptal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Firma Oluştur
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

