<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Ana sayfa - sefer arama formu
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HopBilet - Otobüs Bileti Satış Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">Sefer Ara</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($_SESSION['role'] !== 'admin'): ?>
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
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Giriş Yap</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Kayıt Ol</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="bus bus-1"></div>
        <div class="bus bus-2"></div>
        <div class="bus bus-3"></div>
        <div class="bus bus-4"></div>
        <div class="bus bus-5"></div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="hero-content text-center text-white fade-in-up">
                        <h1 class="display-4 fw-bold mb-4">HopBilet ile Yolculuğa Başla</h1>
                        <p class="lead mb-5">Türkiye'nin en güvenilir otobüs bileti satış platformu</p>
                        
                        <!-- Search Form -->
                        <div class="search-form bg-dark p-4 rounded-3 shadow">
                            <form action="search.php" method="GET">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="departure_city" class="form-label">Nereden</label>
                                        <select class="form-select" id="departure_city" name="departure_city" required>
                                            <option value="">Şehir Seçin</option>
                                            <option value="İstanbul">İstanbul</option>
                                            <option value="Ankara">Ankara</option>
                                            <option value="İzmir">İzmir</option>
                                            <option value="Manisa">Manisa</option>
                                            <option value="Adana">Adana</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="destination_city" class="form-label">Nereye</label>
                                        <select class="form-select" id="destination_city" name="destination_city" required>
                                            <option value="">Şehir Seçin</option>
                                            <option value="İstanbul">İstanbul</option>
                                            <option value="Ankara">Ankara</option>
                                            <option value="İzmir">İzmir</option>
                                            <option value="Manisa">Manisa</option>
                                            <option value="Adana">Adana</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="departure_date" class="form-label">Tarih</label>
                                        <input type="date" class="form-control" id="departure_date" name="departure_date" required>
                                    </div>
                                </div>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        <i class="fas fa-search me-2"></i>Sefer Ara
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-shield-alt fa-3x text-primary"></i>
                    </div>
                    <h4>Güvenli Ödeme</h4>
                    <p class="text-muted">Tüm ödemeleriniz güvenli şekilde işlenir ve korunur.</p>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-clock fa-3x text-primary"></i>
                    </div>
                    <h4>7/24 Hizmet</h4>
                    <p class="text-muted">İstediğiniz zaman bilet alabilir ve işlemlerinizi yapabilirsiniz.</p>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="feature-card text-center p-4">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-ticket-alt fa-3x text-primary"></i>
                    </div>
                    <h4>Kolay İptal</h4>
                    <p class="text-muted">Kalkış saatinden 1 saat öncesine kadar iptal yapabilirsiniz.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Popular Routes -->
    <div class="container my-5">
        <h2 class="text-center mb-5">Popüler Güzergahlar</h2>
        <div class="row">
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="route-card p-3">
                    <h5>İstanbul - Ankara</h5>
                    <p class="text-muted">Başkent'e konforlu yolculuk</p>
                    <span class="badge bg-primary">En Popüler</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="route-card p-3">
                    <h5>İzmir - Ankara</h5>
                    <p class="text-muted">Ege'den başkente</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="route-card p-3">
                    <h5>Manisa - Adana</h5>
                    <p class="text-muted">Akdeniz'e uzanan yol</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>HopBilet</h5>
                    <p>Türkiye'nin en güvenilir otobüs bileti satış platformu</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 HopBilet. Tüm hakları saklıdır.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
