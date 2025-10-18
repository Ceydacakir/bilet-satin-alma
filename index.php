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
                🚀 HopBilet
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
        <div class="stars"></div>
        <div class="twinkling"></div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="hero-content text-center text-white">
                        <div class="hero-title-container mb-4">
                            <h1 class="display-4 fw-bold mb-3">
                                <span class="bouncing-text">🚀</span>
                                <span class="gradient-text">HopBilet</span>
                                <span class="bouncing-text">🚀</span>
                            </h1>
                            <h2 class="display-6 fw-bold mb-3 text-warning">
                                <span class="typing-animation">Yolculuğa Hazır mısın?</span>
                            </h2>
                            <p class="lead mb-4 text-info">
                                <span class="emoji-text">🎯</span>
                                Türkiye'nin en eğlenceli otobüs bileti platformu
                                <span class="emoji-text">🎯</span>
                            </p>
                            <div class="fun-stats mb-4">
                                <span class="stat-item">✨ 1000+ Mutlu Yolcu</span>
                                <span class="stat-item">🎉 50+ Şehir</span>
                                <span class="stat-item">🚌 24/7 Hizmet</span>
                            </div>
                        </div>
                        
                        <!-- Search Form -->
                        <div class="search-form-container">
                            <div class="search-form bg-dark p-4 rounded-3 shadow">
                                <div class="search-header mb-4">
                                    <h3 class="text-center text-white mb-2">
                                        <span class="search-icon">🎫</span>
                                        Biletini Bul, Yolculuğa Başla!
                                        <span class="search-icon">🎫</span>
                                    </h3>
                                    <p class="text-center text-muted">Hızlı, güvenli ve eğlenceli bilet arama</p>
                                </div>
                                <form action="search.php" method="GET">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="departure_city" class="form-label">
                                                <span class="label-icon">📍</span> Nereden
                                            </label>
                                            <select class="form-select" id="departure_city" name="departure_city" required>
                                                <option value="">🏙️ Şehir Seçin</option>
                                                <option value="İstanbul">🏛️ İstanbul</option>
                                                <option value="Ankara">🏛️ Ankara</option>
                                                <option value="İzmir">🌊 İzmir</option>
                                                <option value="Manisa">🍇 Manisa</option>
                                                <option value="Adana">🌶️ Adana</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="destination_city" class="form-label">
                                                <span class="label-icon">🎯</span> Nereye
                                            </label>
                                            <select class="form-select" id="destination_city" name="destination_city" required>
                                                <option value="">🏙️ Şehir Seçin</option>
                                                <option value="İstanbul">🏛️ İstanbul</option>
                                                <option value="Ankara">🏛️ Ankara</option>
                                                <option value="İzmir">🌊 İzmir</option>
                                                <option value="Manisa">🍇 Manisa</option>
                                                <option value="Adana">🌶️ Adana</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="departure_date" class="form-label">
                                                <span class="label-icon">📅</span> Tarih
                                            </label>
                                            <input type="date" class="form-control" id="departure_date" name="departure_date" required>
                                        </div>
                                    </div>
                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg px-5 search-btn">
                                            <span class="btn-icon">🔍</span>
                                            <span class="btn-text">Sefer Ara</span>
                                            <span class="btn-arrow">→</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container my-5">
        <div class="features-header text-center mb-5">
            <h2 class="display-5 fw-bold mb-3">
                <span class="gradient-text">Neden HopBilet?</span>
            </h2>
            <p class="lead text-muted">Süper güçlerimizle yolculuğunu unutulmaz kılıyoruz! ✨</p>
        </div>
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="feature-card text-center p-4 h-100">
                    <div class="feature-icon mb-3">
                        <div class="icon-container">
                            <span class="feature-emoji">🛡️</span>
                        </div>
                    </div>
                    <h4 class="feature-title">Güvenli Ödeme</h4>
                    <p class="text-muted">Ödemeleriniz banka güvenliğinde korunur. Hiç endişelenme! 😌</p>
                    <div class="feature-badge">%100 Güvenli</div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="feature-card text-center p-4 h-100">
                    <div class="feature-icon mb-3">
                        <div class="icon-container">
                            <span class="feature-emoji">⏰</span>
                        </div>
                    </div>
                    <h4 class="feature-title">7/24 Hizmet</h4>
                    <p class="text-muted">Gece yarısı bile bilet alabilirsin! Biz her zaman buradayız 🌙</p>
                    <div class="feature-badge">Her Zaman Açık</div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="feature-card text-center p-4 h-100">
                    <div class="feature-icon mb-3">
                        <div class="icon-container">
                            <span class="feature-emoji">🎫</span>
                        </div>
                    </div>
                    <h4 class="feature-title">Kolay İptal</h4>
                    <p class="text-muted">Plansız mı kaldın? Sorun değil, kolayca iptal edebilirsin! 😊</p>
                    <div class="feature-badge">Esnek İptal</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Popular Routes -->
    <div class="container my-5">
        <div class="routes-header text-center mb-5">
            <h2 class="display-5 fw-bold mb-3">
                <span class="gradient-text">🔥 Popüler Rotalar</span>
            </h2>
            <p class="lead text-muted">En çok tercih edilen güzergahlarımız! Hangi rotayı seçeceksin? 🤔</p>
        </div>
        <div class="row">
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="route-card p-4 h-100">
                    <div class="route-header mb-3">
                        <div class="route-emoji">🏛️</div>
                        <h5 class="route-title">İstanbul - Ankara</h5>
                    </div>
                    <p class="text-muted route-desc">Başkent'e konforlu yolculuk! Büyük şehirler arası en popüler rota 🚀</p>
                    <div class="route-footer">
                        <span class="badge bg-primary route-badge">
                            <span class="badge-icon">⭐</span> En Popüler
                        </span>
                        <span class="route-time">~4.5 saat</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="route-card p-4 h-100">
                    <div class="route-header mb-3">
                        <div class="route-emoji">🌊</div>
                        <h5 class="route-title">İzmir - Ankara</h5>
                    </div>
                    <p class="text-muted route-desc">Ege'nin incisinden başkente! Deniz kokusu eşliğinde yolculuk 🌊</p>
                    <div class="route-footer">
                        <span class="badge bg-success route-badge">
                            <span class="badge-icon">🌊</span> Ege Rüzgarı
                        </span>
                        <span class="route-time">~6 saat</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="route-card p-4 h-100">
                    <div class="route-header mb-3">
                        <div class="route-emoji">🌶️</div>
                        <h5 class="route-title">Manisa - Adana</h5>
                    </div>
                    <p class="text-muted route-desc">Akdeniz'e uzanan yol! Sıcak güneş ve lezzetli yemekler 🍽️</p>
                    <div class="route-footer">
                        <span class="badge bg-warning route-badge">
                            <span class="badge-icon">🌶️</span> Akdeniz
                        </span>
                        <span class="route-time">~8 saat</span>
                    </div>
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
